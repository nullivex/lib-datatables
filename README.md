openlss/lib-datatables
==============

PHP Interface for jQuery Datatables (http://datatables.net). Simple standalone, non-framework based.

This document assumes you have a working datatables frontend. Just point it to the usage code below.

Usage
===

```php
$obj = Datatables::_get()
	->setDB(Db::_get()) //add our database object
	->setDataModel('\BrowserModel')	//set datamodel to use for row formatting
	->setColumns(array('engine','browser','platform','version','grade')) //set column defs
	->setPrimary('id') //set primary column
	->setTable('table') //set sql table to use
	->setupFromRequest() //setup the object from $_REQUEST
	->process(); //process the request
echo $obj; //uses __toString to output json
exit;
```

openlss/lib-datamodels Usage
===

```php
class BrowserModel extends \LSS\DataModel {
	//format version
	public function getVersion(){
		return empty($this->version) ? '-' : $this->version;
	}
}
```

Methods
===

### (object) DataTables::_get()
Returns a new object same as
```php
$obj = new DataTables();
```

### ($this) DataTables::setDB(\LSS\Db $obj)
Set the LSS\Db object to call queries on

### ($this) DataTables::setColumns($array)
Set the array of columns in the table (should match SQL names)

### ($this) DataTables::setPrimary($val)
Set the primary column name (can be an array for multiple columns)

### ($this) DataTables::setTable($val)
Set the database table to use (can be multiple just separate with commas)
NOTE: currently does not support joins

### ($this) DataTables::setDataModel($val)
Set the datamodel class to use. By Default uses \LSS\DataModel with no extensions

### ($this) DataTables::setParams($name,$val)
Store arbitrary params for use in resulting JSON

### (int) DataTables::getColumnCount()
Returns the number of columns set (caches the result for speed)

### (string) DataTables::getColumnByKey($key)
Returns the column name by index number

### (mixed) DataTables::getParam($key)
Returns the value of a set param by name (key)

### (string) DataTables::getJSON()
Returns JSON built from result array

### (array) DataTables::getResult()
Return the built result array

### ($this) DataTables::setupFromRequest()
Sets up all the various portions needed for query generation from $_REQUEST

### ($this) DataTables::setupPaging($start=null,$limit=null)
Setup paging for SQL

### ($this) DataTables::setupOrdering((mixed...))
Dynamic function for setting up ordering
Argument format is:
 * col_number			Number of columns
 * col_x_sortable		Is column sortable
 * col_x_key			Column number related to $this->columns
 * col_x_sort_dir		Sort direction of the column

### ($this) DataTables::setupFiltering((mixed...))
NOTE 	this does not match the built-in DataTables filtering which does it
		word by word on any field. It's possible to do here, but concerned about efficiency
		on very large tables, and MySQL's regex functionality is very limited
Dynamic function for setting up filtering
Argument format is:
 * search_string		User search string
 * col_x_searchable	Column ID that is searchable
 * col_x_search_string	Individual search string per column

### ($this) DataTables::setupQueries()
Sets up SQL queries based on collected data

### ($this) DataTables::proces()
Run queries and build result array

### (string) DataTables::__toString()
Returns the resulting JSON when the object is used as a string
