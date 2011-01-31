<?php
/**
 * Standard error messaging config
 */

$paco_errors =  
array(
	'errors'=>array(
		'name'=>array(
			'required'=>'The name field cannot be empty',
			'alphanumeric'=>'The name field can only contain alphanumeric characters'
		),
		'age'=>array(
			'required'=>'The age field cannot be empty',
			'number'=>'Age has to contain numeric values only'
		)
	)
);
?>
