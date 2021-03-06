<?php
/**
 * @package Okey DOC 1.x
 * @copyright Copyright (c)2014 - 2017 Lucas Sanner
 * @license GNU General Public License version 3, or later
 * @contact lucas.sanner@gmail.com
 */

defined('_JEXEC') or die;

// Base this model on the backend version.
require_once JPATH_ADMINISTRATOR.'/components/com_okeydoc/models/document.php';


//Inherit the backend version.
class OkeydocModelForm extends OkeydocModelDocument
{
  /**
   * Model typeAlias string. Used for version history.
   *
   * @var        string
   */
  public $typeAlias = 'com_okeydoc.document';


  /**
   * Method to auto-populate the model state.
   *
   * Note. Calling getState in this method will result in recursion.
   *
   * @return  void
   *
   * @since   1.6
   */
  protected function populateState()
  {
    $app = JFactory::getApplication();

    // Load state from the request.
    $pk = $app->input->getInt('d_id');
    $this->setState('document.id', $pk);

    //Retrieve a possible category id from the url query.
    $this->setState('document.catid', $app->input->getInt('catid'));

    //Retrieve a possible encoded return url from the url query.
    $return = $app->input->get('return', null, 'base64');
    $this->setState('return_page', base64_decode($return));

    // Load the global parameters of the component.
    $params = $app->getParams();
    $this->setState('params', $params);

    $this->setState('layout', $app->input->getString('layout'));
  }


  /**
   * Method to get document data.
   *
   * @param   integer  $itemId  The id of the document.
   *
   * @return  mixed  Content item data object on success, false on failure.
   */
  public function getItem($itemId = null)
  {
    $itemId = (int) (!empty($itemId)) ? $itemId : $this->getState('document.id');

    // Get a row instance.
    $table = $this->getTable();

    // Attempt to load the row.
    //Notes: If it's a new item, load function just return true.
    $return = $table->load($itemId);

    // Check for a table object error.
    if($return === false && $table->getError()) {
      $this->setError($table->getError());
      return false;
    }

    //Get the fields of the table as an array
    $properties = $table->getProperties(1);
    //then convert the array into an object.
    $item = JArrayHelper::toObject($properties, 'JObject');

    //Note: params fields are missing on purpose in the xml form as
    //params cannot be set on frontend.
    //All of the document items created on frontend has an empty
    //params value.

    // Convert params field to Registry.
    $item->params = new JRegistry;
    $item->params->loadString($item->params);

    // Compute selected asset permissions.
    $user = JFactory::getUser();
    $userId = $user->get('id');
    $asset = 'com_okeydoc.document.'.$item->id;

    // Check general edit permission first.
    if($user->authorise('core.edit', $asset)) {
      $item->params->set('access-edit', true);
    }
    // Now check if edit.own is available.
    elseif(!empty($userId) && $user->authorise('core.edit.own', $asset)) {
      // Check for a valid user and that they are the owner.
      if($userId == $item->created_by) {
	$item->params->set('access-edit', true);
      }
    }

    // Existing item
    if($itemId) {
      // Check edit state permission.
      $item->params->set('access-change', $user->authorise('core.edit.state', $asset));

      //Set up the text to display in the editor.
      $item->documenttext = $item->introtext;
      if(!empty($item->fulltext)) {
	$item->documenttext .= '<hr id="system-readmore" />'.$item->fulltext;
      }
    }
    else { // New item.
      
      $catId = (int) $this->getState('document.catid');

      if($catId) {
	//Check the change access in this specific category.
	$item->params->set('access-change', $user->authorise('core.edit.state', 'com_okeydoc.category.'.$catId));
	$item->catid = $catId;
      }
      else { //Check the general change access.
	$item->params->set('access-change', $user->authorise('core.edit.state', 'com_okeydoc'));
      }
    }

    // Convert the metadata field to an array.
    $registry = new JRegistry;
    $registry->loadString($item->metadata);
    $item->metadata = $registry->toArray();

    //Get the document tags.
    $item->tags = new JHelperTags;
    $item->tags->getTagIds($item->id, 'com_okeydoc.document');
    $item->metadata['tags'] = $item->tags;

    return $item;
  }


  /**
   * Get the return URL.
   *
   * @return  string	The return URL.
   *
   * @since   1.6
   */
  public function getReturnPage()
  {
    return base64_encode($this->getState('return_page'));
  }

  /**
   * Method to save the form data.
   *
   * @param   array  $data  The form data.
   *
   * @return  boolean  True on success.
   *
   * @since   3.2
   */
  public function save($data)
  {
    return parent::save($data);
  }
}
