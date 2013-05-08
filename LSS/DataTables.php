<?php
/**
 *  OpenLSS - Lighter Smarter Simpler
 *
 *	This file is part of OpenLSS.
 *
 *	OpenLSS is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU Lesser General Public License as
 *	published by the Free Software Foundation, either version 3 of
 *	the License, or (at your option) any later version.
 *
 *	OpenLSS is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU Lesser General Public License for more details.
 *
 *	You should have received a copy of the 
 *	GNU Lesser General Public License along with OpenLSS.
 *	If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Script:    DataTables server-side script for PHP and MySQL
 * Copyright: 2010 - Allan Jardine, 2012 - Chris Wright
 * License:   GPL v2 or BSD (3-point)
 */
namespace LSS;
use \Exception;

//usage
/*
$obj = DataTables::_get()
	->setDB(Db::_get()) //add our database object
	->setDataModel('\BrowserModel')	//set datamodel to use for row formatting
	->setColumns(array('engine','browser','platform','version','grade')) //set column defs
	->setPrimary('id') //set primary column
	->setTable('table') //set sql table to use
	->setupFromRequest() //setup the object from $_REQUEST
	->process(); //process the request
echo $obj; //uses __toString to output json
exit;
*/

//requires openlss/lib-datamodel -- example
/*
class BrowserModel extends \LSS\DataModel {
	//format version
	public function getVersion(){
		return empty($this->version) ? '-' : $this->version;
	}
}
*/

class DataTables {

	//general settings for result
	public $params = array();
	//database object to use
	public $db = null;
	//data callback
	public $data_callback = null;
	//data callback additional args
	public $data_callback_args = array();
	//datamodel
	public $datamodel = '\LSS\DataModel';
	//columns to iterate
	public $columns = array();
	//cache column count
	private $column_count = null;
	//primary column(s)
	public $primary = array();
	//table id to fill with data
	public $table = 'datatable';
	//sql limit part
	public $sql_limit = null;
	//sql order part
	public $sql_order = null;
	//sql where part
	public $sql_where = null;
	//sql where args
	public $sql_where_args = array();
	//sql query (main query)
	public $sql_query = null;
	//sql query length (number of rows)
	public $sql_query_length = null;
	//sql total query length (total number of table records)
	public $sql_query_total_length = null;
	//result array (to be converted to json)
	public $result = array();

	public static function _get(){
		return new static();
	}

	public function setDB(LSS\Db $obj){
		$this->db = $obj;
		return $this;
	}

	public function setColumns($array){
		$this->columns = $array;
		return $this;
	}

	public function setPrimary($val){
		if(!is_array($val)) $val = array($val);
		$this->primary = $val;
		return $this;
	}
 
	public function setTable($val){
		$this->table = $val;
		return $this;
	}

	public function setDataCallback(){
		$args = func_get_args();
		$this->data_callback = array_shift($args);
		$this->data_callback_args = $args;
		return $this;
	}

	public function setDataModel($val){
		$this->datamodel = $val;
		return $this;
	}

	public function setParam($name,$val){
		mda_set($this->params,$name,$val);
		return $this;
	}

	public function getColumns(){
		return $this->columns;
	}

	public function getColumnCount(){
		if(is_null($this->column_count))
			$this->column_count = count($this->columns);
		return $this->column_count;
	}

	public function getColumnByKey($key){
		if(!isset($this->columns[$key]))
			throw new Exception('Tried to get a column that doesnt exist: '.$key);
		return $this->columns[$key];
	}

	public function getColumnsAsKeys(){
		$arr = array();
		foreach($this->columns AS $col)
			$arr[$col] = null;
		return $arr;
	}

	public function getParam($key){
		return mda_get($this->params,$key);
	}

	public function getJSON(){
		return json_encode($this->result);
	}

	public function getResult(){
		return $this->result;
	}

	public function setupFromRequest(){
		//params
		$this->setParam('sEcho',req('sEcho'));
		//paging
		$this->setupPaging(req('iDisplayStart'),req('iDisplayLength'));
		//ordering
		if(!is_null(req('iSortCol_0'))){
			$args = array(req('iSortingCols'));
			for($i=0;$i<intval(req('iSortingCols'));$i++){
				$args[] = req('bSortable_'.intval(req('iSortCol_'.$i)));
				$args[] = req('iSortCol_'.$i);
				$args[] = req('sSortDir_'.$i);
			}
			call_user_func_array(array($this,'setupOrdering'),$args);
		}
		//filtering
		if(!is_null(req('sSearch'))){
			$args = array(req('sSearch'));
			for($i=0;$i<$this->getColumnCount();$i++){
				$args[] = req('bSearchable_'.$i);
				$args[] = req('sSearch_'.$i);
			}
			call_user_func_array(array($this,'setupFiltering'),$args);
		}
		//queries
		$this->setupQueries();
		return $this;
	}

	public function setupPaging($start=null,$length=null){
		if(is_null($start) || is_null($length) || $length == '-1')
			return $this;
		$this->sql_limit = ' LIMIT '.$start.','.$length.' ';
		return $this;
	}

	//Dynamic function for setting up ordering
	//	Argument format is:
	//		col_number			Number of columns
	//		col_x_sortable		Is column sortable
	//		col_x_key			Column number related to $this->columns
	//		col_x_sort_dir		Sort direction of the column
	
	public function setupOrdering(){
		$args = func_get_args();
		//grab number of columns (first argument)
		$cols = array_shift($args);
		if(!$cols) return $this;
		$this->sql_order = ' ORDER BY ';
		for($i=0;$i<intval($cols);$i++){
			//grab arguments for this col
			$sortable = array_shift($args);
			$col_key = intval(array_shift($args));
			$sort_dir = array_shift($args);
			//check if we are sorting this column
			if($sortable != "true") continue;
			//build SQL for sorting
			$this->sql_order .= '`'.$this->getColumnByKey($col_key).'`';
			$this->sql_order .= ($sort_dir === 'asc' ? 'asc' : 'desc').',';
		}
		//trim excess commas and space
		$this->sql_order = trim(rtrim($this->sql_order,',')).' ';
		//if nothing got appended clear the clause (more of a fail-safe)
		if($this->sql_order == 'ORDER BY ')
			$this->sql_order = null;
		return $this;
	}

	/*
	 * Filtering
	 * NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here, but concerned about efficiency
	 * on very large tables, and MySQL's regex functionality is very limited
	 */
	//Dynamic function for setting up filtering
	//	Argument format is:
	//		search_string		User search string
	//		col_x_searchable	Column ID that is searchable
	//		col_x_search_string	Individual search string per column
	public function setupFiltering(){
		//get func args and global search string
		$args = func_get_args();
		$search_string = array_shift($args);
		//define vars
		$col_searchable = $col_search = array();
		$global_search = $ind_search = null;
		$global_search_args = $ind_search_args = array();
		//global filtering
		if(!empty($search_string)){
			$global_search = ' WHERE (';
			for($i=0;$i<$this->getColumnCount();$i++){
				//check if we can search this column
				$col_searchable[$i] = array_shift($args);
				$col_search[$i] = array_shift($args);
				if($col_searchable[$i] !== 'true') continue;
				//build SQL to searh this column
				$global_search .= '`'.$this->getColumnByKey($i).'` LIKE ? OR ';
				$global_search_args[] = '%'.$search_string.'%';
			}
			//trim excess OR stmts
			$global_search = trim(rtrim($global_search,'OR '));
			//close pars
			$global_search .= ') ';
		}
		//individual column filtering
		for($i=0;$i<$this->getColumnCount();$i++){
			if(!isset($col_searchable[$i]) || $col_searchable[$i] !== 'true' || empty($col_search[$i])) continue;
			//setup our where string
			if(is_null($global_search) && is_null($ind_search))
				$ind_search = 'WHERE ';
			else
				$ind_search .= ' AND ';
			//add column SQL
			$ind_search = '`'.$this->getColumnByKey($i).'` LIKE ? ';
			$ind_search_args[] = '%'.$col_search[$i].'%';
		}
		//build where statement
		$this->sql_where = $global_search.$ind_search;
		$this->sql_where_args = array_merge($global_search_args,$ind_search_args);
		return $this;
	}

	public function setupQueries(){
		$this->sql_query =
			'SELECT SQL_CALC_FOUND_ROWS '.implode(',',$this->columns)
			.' FROM '.$this->table
			.$this->sql_where
			.$this->sql_order
			.$this->sql_limit;
		$this->sql_query_length = 'SELECT FOUND_ROWS()';
		$this->sql_query_total_length = 'SELECT COUNT('.implode(',',$this->primary).') FROM '.$this->table;
		return $this;
	}

	public function process(){
		if(!is_null($this->data_callback)){
			list($results,$count_results,$count_total) = call_user_func_array(
				 $this->data_callback
				,array_merge(
					array(
						 $this->columns
						,array($this->sql_where,$this->sql_where_args)
						,$this->sql_order
						,$this->sql_limit
					)
					,$this->data_callback_args
				)
			);
		} else {
			//run queries
			$results = $this->db->fetchAll($this->sql_query);
			$count_results = $this->db->fetch($this->sql_query_length);
			$count_total = $this->db->fetch($this->sql_query_total_length);
		}
		//setup result array
		$this->result = array_merge(
			$this->params
			,array(
				 'iTotalRecords'			=>		$count_total
				,'iTotalDisplayRecords'		=>		$count_results
				,'aaData'					=>		array()
			)
		);
		//use openlss/lib-datamodel to format output
		foreach($results as $row){
			$this->result['aaData'][] = call_user_func(
				$this->datamodel.'::_setup',$row
			)->_getColumns($this->getColumns(),\LSS\DataModel::KEYS_NUMERIC);
		}
		return $this;
	}

	public function __toString(){
		return $this->getJSON();
	}

}
