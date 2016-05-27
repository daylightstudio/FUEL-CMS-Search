<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 

require_once(FUEL_PATH.'models/'.((FUEL_VERSION < 1.4) ? 'b' : 'B').'ase_module_model.php');

class Search_model extends Base_module_model {
	
	public $required = array('location', 'scope', 'title', 'content');
	public $record_class = 'Search_item';
	public $auto_encode_entities = array('title');
	
	public $tmp_table = 'fuel_search_tmp';
	protected $_orig_table_name = 'search';

	function __construct()
	{
		parent::__construct($this->_orig_table_name, SEARCH_FOLDER);
	}

	function set_table($table)
	{
		$this->table_name = $table;
	}

	function find_by_keyword($q, $limit = NULL, $offset = NULL, $excerpt_limit = 200)
	{
		$this->_find_keyword_where($q);

		$this->db->select($this->_tables['search'].'.location');
		$this->db->select($this->_tables['search'].'.title');
		$this->db->select($this->_tables['search'].'.date_added');
		$this->db->select($this->_tables['search'].'.content');
		$this->db->select($this->_tables['search'].'.language');
		$this->db->select('IF(excerpt = "", SUBSTRING('.$this->_tables['search'].'.content, 1, '.$excerpt_limit.'), excerpt) AS excerpt', FALSE);
		$limit = (int) $limit;
		$this->db->limit($limit);
		
		if (!empty($offset))
		{
			$offset = (int) $offset;
			$this->db->offset($offset);
		}
		$results = $this->find_all();
		//$this->debug_query();

		return $results;
	}
	
	function find_by_keyword_count($q)
	{
		$this->_find_keyword_where($q, TRUE);
		$count = $this->db->count_all_results($this->table_name);
		return $count;
	}
	
	protected function _find_keyword_where($q, $is_count = FALSE)
	{
		$CI =& get_instance();
		$full_text_fields = array('location', 'title', 'content');
		$full_text_indexed = implode($full_text_fields, ', ');
	
		$q = trim(strtolower($q)); // trim the right and left from whitespace
		$q = preg_replace("#([[:space:]]{2,})#",' ',$q); // remove multiple spaces
		$q_len = strlen($q);
		
		if ($q_len >= 4 AND (strtolower($CI->fuel->search->config('query_type')) == 'match' OR strtolower($CI->fuel->search->config('query_type')) == 'match boolean'))
		{
			$q = $this->db->escape($q);
			if (strtolower($CI->fuel->search->config('query_type')) == 'match boolean')
			{
				$this->db->where('MATCH('.$full_text_indexed.') AGAINST ('.$q.' IN BOOLEAN MODE)');
			}
			else
			{
				$this->db->where('MATCH('.$full_text_indexed.') AGAINST ('.$q.')');
			}
			if ( ! $is_count)
			{
				$this->db->select('MATCH ('.$full_text_indexed.') AGAINST ('.$q.')  AS relevance ', FALSE);
				$this->db->order_by('relevance desc');
			}
		}
		else
		{
			$q = $this->db->escape_str($q);
			$like_or = array();
			foreach ($full_text_fields as $field)
			{
				$like_or[] = $field.' LIKE "%'.$q.'%"';
				if ( ! $is_count) $select[] = '(CASE WHEN `'.$field.'` LIKE "%'.$q.'%" THEN 1 ELSE 0 END)';
			}
			
			$like_or_str = implode(' OR ', $like_or);
			unset($like_or);
			$this->db->where('('.$like_or_str.')');
			if ( ! $is_count)
			{
				$this->db->select('('.implode(' + ', $select).') AS relevance', false);
				$this->db->order_by('relevance desc');
			}
		}

		// manage the language
		if ($this->fuel->language->has_multiple())
		{
			$language = $CI->fuel->language->detect();

			if (!empty($language))
			{
				$this->db->where('('.$this->_tables['search'].'.language="'.$language.'" OR '.$this->_tables['search'].'.language="")');
			}
		}
	}
	
	function find_by_location($location)
	{
		return $this->find_one(array('location' => $location));
	}
	
	function form_fields($values = array(), $related = array())
	{
		$fields = parent::form_fields($values, $related);
		$fields['content']['class'] = 'no_editor';
		$fields['content']['type'] = 'text';
		return $fields;
	}
	
	
	
}

class Search_item_model extends Base_module_record {
	
	
	function get_url()
	{
		return site_url($this->location);
	}
	
	function get_excerpt($char_limit = 100, $start_word = NULL, $variance = 50)
	{
		$field = 'excerpt';
		if (empty($this->_fields['excerpt']) OR strlen($this->_fields['excerpt']) < $char_limit)
		{
			$field = 'content';
		}
		return $this->excerpt($field, $char_limit, $start_word, $variance);
	}

	function get_content_excerpt($char_limit = 100, $start_word = NULL, $variance = 50)
	{
		return $this->excerpt('content', $char_limit, $start_word, $variance);
	}

	function excerpt($field, $char_limit = 100, $start_word = '', $variance = 50)
	{

		$excerpt = $this->_fields[$field];
		$start_word_pos = is_string($start_word) ? mb_stripos($excerpt, $start_word) : 0;

		$start_pos = $start_word_pos - $variance;
		if ($start_pos < 0)
		{
			$start_pos = 0;
		}
		
		$excerpt = mb_substr($excerpt, $start_pos);
		if (!empty($char_limit))
		{
			$excerpt = character_limiter($excerpt, $char_limit);
		}

		if ($start_pos > 0)
		{
			// cut it to first word by looking for the first space
			$start_pos2 = mb_strpos($excerpt, ' ');
			$excerpt = mb_substr($excerpt, $start_pos2);

			$excerpt = '&#8230;'.$excerpt;
		}
		return $excerpt;
	}
}
