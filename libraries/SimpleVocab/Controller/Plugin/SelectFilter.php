<?php
/**
 * Simple Vocab
 * 
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Filter selected form elements to a select menu containing custom terms.
 * 
 * @package Omeka\Plugins\SimpleVocab
 */
class SimpleVocab_Controller_Plugin_SelectFilter extends Zend_Controller_Plugin_Abstract
{
    /**
     * All routes that render an item element form, including those requested 
     * via AJAX.
     * 
     * @var array
     */
    protected $_defaultRoutes = array(
        array('module' => 'default', 'controller' => 'items', 
              'actions' => array('add', 'edit', 'change-type')), 
        array('module' => 'default', 'controller' => 'elements', 
              'actions' => array('element-form')), 
    );
    
    /**
     * Cached vocab terms.
     */
    protected $_simpleVocabTerms;
    
    /**
     * Set the filters pre-dispatch only on configured routes.
     * 
     * @param Zend_Controller_Request_Abstract
     */
    public function preDispatch($request)
    {
        $db = get_db();
        
        // Some routes don't have a default module, which resolves to NULL.
        $currentModule = is_null($request->getModuleName()) ? 'default' : $request->getModuleName();
        $currentController = $request->getControllerName();
        $currentAction = $request->getActionName();
        
        // Allow plugins to register routes that contain form inputs rendered by 
        // Omeka_View_Helper_ElementForm::_displayFormInput().
        $routes = apply_filters('simple_vocab_routes', $this->_defaultRoutes);
        
        // Apply filters to defined routes.
        foreach ($routes as $route) {
            
            // Check registered routed against the current route.
            if ($route['module'] != $currentModule 
             || $route['controller'] != $currentController 
             || !in_array($currentAction, $route['actions']))
            {
                continue;
            }
            
            // Add the filters if the current route is registered. Cache the 
            // vocab terms for use by the filter callbacks.
            $select = $db->getTable('SimpleVocabTerm')->getSelect()
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns(array('element_id', 'terms'));
            $this->_simpleVocabTerms = $db->fetchPairs($select);
            foreach ($this->_simpleVocabTerms as $element_id => $terms) {
                $element = $db->getTable('Element')->find($element_id);
                $elementSet = $db->getTable('ElementSet')->find($element->element_set_id);
                add_filter(array('ElementInput', 'Item', $elementSet->name, $element->name), 
                           array($this, 'filterElementInput'));
            }
            // Once the filter is applied for one route there is no need to 
            // continue looping the routes.
            break;
        }
    }
    
    /**
     * Filter the element input.
     * 
     * @param array $components
     * @param array $args
     * @return array
     */
    public function filterElementInput($components, $args)
    {
        // Use the cached vocab terms instead of 
        $terms = explode("\n", $this->_simpleVocabTerms[$args['element']->id]);

	// fcd1, 03/04/14:
	// Added line of code below so current value in field is also allowed
	// If did not do this, the current value would be removed if it was not
	// one of the terms in the vocabulary

	// fcd1, 4/22/14: Add code to change label for deprecated value
	if ( ($args['value']) && (!in_array($args['value'],$terms)) ) {
	  //$selectTerms[$args['value']] = '(DEPRECATED!) ' . $args['value'];
	  $selectTerms = array($args['value'] => '(INVALID VALUE!) ' . $args['value']);
	  $selectTerms = $selectTerms + array('' => 'Select Below') + array_combine($terms, $terms);
	  $style = array('style' => 'width: 350px;color:red;');
	} else {
	  $selectTerms = array('' => 'Select Below') + array_combine($terms, $terms);
	  $selectTerms[$args['value']] = $args['value'];
	  $style = array('style' => 'width: 350px;');
	}

        $components['input'] = get_view()->formSelect(
            $args['input_name_stem'] . '[text]', 
            $args['value'], 
	    // fcd1, 4/22/14: set attribs above, according to invalid value or not
	    $style,
            $selectTerms
        );
        $components['html_checkbox'] = false;
        return $components;
    }
}
