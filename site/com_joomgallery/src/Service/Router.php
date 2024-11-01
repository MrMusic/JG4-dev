<?php
/**
******************************************************************************************
**   @version    4.0.0-dev                                                              **
**   @package    com_joomgallery                                                        **
**   @author     JoomGallery::ProjectTeam <team@joomgalleryfriends.net>                 **
**   @copyright  2008 - 2023  JoomGallery::ProjectTeam                                  **
**   @license    GNU General Public License version 3 or later                          **
*****************************************************************************************/

namespace Joomgallery\Component\Joomgallery\Site\Service;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Menu\AbstractMenu;
use \Joomla\Database\ParameterType;
use \Joomla\Database\DatabaseInterface;
use \Joomla\CMS\Component\Router\RouterView;
use \Joomla\CMS\Component\Router\RouterViewConfiguration;
use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Categories\CategoryFactoryInterface;
use \Joomla\CMS\Component\Router\Rules\MenuRules;
use \Joomla\CMS\Component\Router\Rules\NomenuRules;
use \Joomla\CMS\Component\Router\Rules\StandardRules;
use \Joomgallery\Component\Joomgallery\Administrator\Table\CategoryTable;

/**
 * Joomgallery Router class
 *
 */
class Router extends RouterView
{
  /**
	 * Param to use ids in URLs
	 *
	 * @var    bool
	 *
	 * @since  4.0.0
	 */
	private $noIDs;

  /**
	 * Databse object
	 *
	 * @var    DatabaseInterface
	 *
	 * @since  4.0.0
	 */
	private $db;

  /**
	 * The category cache
	 *
	 * @var    array
	 *
	 * @since  4.0.0
	 */
	private $categoryCache = [];

	public function __construct(SiteApplication $app, AbstractMenu $menu, ?CategoryFactoryInterface $categoryFactory, DatabaseInterface $db)
	{
    parent::__construct($app, $menu);
    $params = $this->app->getParams('com_joomgallery');
    
    $this->db    = $db;
		$this->noIDs = (bool) $params->get('sef_ids');

    $gallery = new RouterViewConfiguration('gallery');
    $this->registerView($gallery);

    $categories = new RouterViewConfiguration('categories');
    $this->registerView($categories);

    $category = new RouterViewConfiguration('category');
    $category->setKey('id')->setNestable()->setParent($gallery);
    $this->registerView($category);

    $categoryform = new RouterViewConfiguration('categoryform');
    $categoryform->setKey('id');
    $this->registerView($categoryform);

		$images = new RouterViewConfiguration('images');
    $images->setParent($gallery);
		$this->registerView($images);

    $image = new RouterViewConfiguration('image');
    $image->setKey('id')->setParent($images);
    $this->registerView($image);

    $imageform = new RouterViewConfiguration('imageform');
    $imageform->setKey('id');
    $this->registerView($imageform);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

  /**
   * Method to get the segment for a gallery view
   *
   * @param   string  $id     ID of the image to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getGallerySegment($id, $query)
  {
    return array();
  }
	
  /**
   * Method to get the segment for an image view
   *
   * @param   string  $id     ID of the image to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getImageSegment($id, $query)
  {
    if(!\strpos($id, ':'))
    {
      $dbquery = $this->db->getQuery(true);

      $dbquery->select($this->db->quoteName('alias'))
        ->from($this->db->quoteName(_JOOM_TABLE_IMAGES))
        ->where($this->db->quoteName('id') . ' = :id')
        ->bind(':id', $id, ParameterType::INTEGER);
      $this->db->setQuery($dbquery);

      $id .= ':' . $this->db->loadResult();
    }

    if($this->noIDs && \strpos($id, ':') !== false)
    {
      list($void, $segment) = \explode(':', $id, 2);

      return array($void => $segment);
    }

    return array((int) $id => $id);
  }

  /**
   * Method to get the segment(s) for an imageform
   *
   * @param   string  $id     ID of the imageform to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getImageformSegment($id, $query)
  {
    return $this->getImageSegment($id, $query);
  }

  /**
   * Method to get the segment(s) for an image
   *
   * @param   string  $id     ID of the image to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getImagesSegment($id, $query)
  {
    return $this->getImageSegment($id, $query);
  }

  /**
   * Method to get the segment(s) for an category
   *
   * @param   string  $id     ID of the category to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *                          array(id = id:alias, parentid: parentid:parentalias)
   *
   * @return  array|string  The segments of this item
   */
  public function getCategorySegment($id, $query)
  {
    $category = $this->getCategory((int) $id, 'route_path', true);

    if($category)
    {
      // Replace root with categories
      if($root_key = \key(\preg_grep('/\broot\b/i', $category->route_path)))
      {
        $category->route_path[$root_key] = \str_replace('root', 'categories', $category->route_path[$root_key]);
      }

      if($this->noIDs && \strpos(\reset($category->route_path), ':') !== false)
      {
        foreach($category->route_path as &$segment)
        {
          list($id, $segment) = \explode(':', $segment, 2);
        }
      }

      return $category->route_path;
    }

    return array();
  }

  /**
   * Method to get the segment(s) for an categoryform
   *
   * @param   string  $id     ID of the categoryform to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getCategoryformSegment($id, $query)
  {
    return $this->getCategorySegment($id, $query);
  }

  /**
   * Method to get the segment(s) for a category
   *
   * @param   string  $id     ID of the category to retrieve the segments for
   * @param   array   $query  The request that is built right now
   *
   * @return  array|string  The segments of this item
   */
  public function getCategoriesSegment($id, $query)
  {
    return $this->getCategorySegment($id, $query);
  }

  /**
   * Method to get the segment for a gallery view
   *
   * @param   string  $segment  Segment of the image to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getGalleryId($segment, $query)
  {
    return 0;
  }
	
  /**
   * Method to get the segment for an image view
   *
   * @param   string  $segment  Segment of the image to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getImageId($segment, $query)
  {
    if($this->noIDs)
    {
      $dbquery = $this->db->getQuery(true);

      $dbquery->select($this->db->quoteName('id'))
        ->from($this->db->quoteName(_JOOM_TABLE_IMAGES))
        ->where($this->db->quoteName('alias') . ' = :alias')
        ->bind(':alias', $segment);

      if(\key_exists('catid', $query))
      {
        $dbquery->where($this->db->quoteName('catid') . ' = :catid');
        $dbquery->bind(':catid', $query['id'], ParameterType::INTEGER);
      }

      $this->db->setQuery($dbquery);

      return (int) $this->db->loadResult();
    }

    return (int) $segment;
  }

  /**
   * Method to get the segment(s) for an imageform
   *
   * @param   string  $segment  Segment of the imageform to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getImageformId($segment, $query)
  {
    return $this->getImageId($segment, $query);
  }

  /**
   * Method to get the segment(s) for an image
   *
   * @param   string  $segment  Segment of the image to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getImagesId($segment, $query)
  {
    return $this->getImageId($segment, $query);
  }

  /**
   * Method to get the segment(s) for an category
   *
   * @param   string  $segment  Segment of the category to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getCategoryId($segment, $query)
  {
    if(isset($query['id']) && ($query['id'] === 0 || $query['id'] === '0'))
    {
      // Root element of nestable content in core must have the id=0
      // But JoomGallery category root has id=1
      $query['id'] = 1;
    }

    if(\strpos($segment, 'categories'))
    {
      // If 'categories' is in the segment, means that we are looking for the root category
      $segment = \str_replace('categories', 'root', $segment);
    }

    if(isset($query['id']))
    {
      $category = $this->getCategory((int) $query['id'], 'children', true);

      if($category)
      {
        foreach($category->children as $child)
        {
          if($this->noIDs)
          {
            if($child['alias'] == $segment)
            {
              return $child['id'];
            }
          }
          else
          {
            if($child['id'] == (int) $segment)
            {
              return $child['id'];
            }
          }
        }
      }  
    }

    return false;
  }

  /**
   * Method to get the segment(s) for an categoryform
   *
   * @param   string  $segment  Segment of the categoryform to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getCategoryformId($segment, $query)
  {
    return $this->getCategoryId($segment, $query);
  }

  /**
   * Method to get the segment(s) for an category
   *
   * @param   string  $segment  Segment of the category to retrieve the ID for
   * @param   array   $query    The request that is parsed right now
   *
   * @return  mixed   The id of this item or false
   */
  public function getCategoriesId($segment, $query)
  {
    return $this->getCategoryId($segment, $query);
  }

	/**
	 * Method to get categories from cache
	 *
	 * @param   int             $id         It of the category
   * @param   string          $available  The property to make available in the category
	 *
	 * @return  CategoryTable   The category table object
	 *
	 * @since   4.0.0
   * @throws  \UnexpectedValueException
	 */
	private function getCategory($id, $available = null, $root = true): CategoryTable
	{
    // Load the category table
		if(!isset($this->categoryCache[$id]))
		{
      $table = $this->app->bootComponent('com_joomgallery')->getMVCFactory()->createTable('Category', 'administrator');
      $table->load($id);
      $this->categoryCache[$id] = $table;
		}

    // Make node tree available in cache
    if(!\is_null($available) && !isset($this->categoryCache[$id]->{$available}))
    {
      switch ($available) {
        case 'route_path':
          $this->categoryCache[$id]->{$available} = $this->categoryCache[$id]->getRoutePath($root, 'route_path');
          break;
        
        case 'children':
          $this->categoryCache[$id]->{$available} = $this->categoryCache[$id]->getNodeTree('children', true, $root);
          break;

        case 'parents':
          $this->categoryCache[$id]->{$available} = $this->categoryCache[$id]->getNodeTree('children', true, $root);
          break;
        
        default:
          throw new \UnexpectedValueException('Requested property ('.$available.') can to be made available in a category.');
          break;
      }
    }

		return $this->categoryCache[$id];
	}
}
