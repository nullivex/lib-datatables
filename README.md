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
