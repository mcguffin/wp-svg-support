<?php

function xml_adopt($root, $new, $namespace = null ) {
	// first add the new node
	$node = $root->addChild($new->getName(), (string) $new, $namespace);

	// add any attributes for the new node
	foreach($new->attributes() as $attr => $value) {
		$node->addAttribute($attr, $value);
	}
	// get all namespaces, include a blank one
	$namespaces = array_merge(array(null), $new->getNameSpaces(true));

	// add any child nodes, including optional namespace
	foreach($namespaces as $space) {
		foreach ($new->children($space) as $child) {
			xml_adopt($node, $child, $space);
		}
	}
}
function xml_wrap( &$parent , &$wrap_node ) {

	// append to parent
	foreach ($parent->xpath('/*/*') as $i => $c) {
		xml_adopt($wrap_node , $c);
		unset($c[0]);
	}

	xml_adopt($parent , $wrap_node);
}