<?php
namespace Halftheory\Themes\Halftheory_Clean;

use Halftheory\Lib\Theme;

#[AllowDynamicProperties]
class Halftheory_Clean_Theme extends Theme {

	public static $handle;
	protected static $instance;
	protected $data = array();

	public function __construct( $autoload = false ) {
		static::$handle = 'wp-halftheory-clean';
		parent::__construct($autoload);
	}

	protected function autoload() {
		$this->load_filters(new Halftheory_Clean_Filters(true));
		$array = array(
			'admin-common',
			'feed-common',
			'gallery-common',
			'mail-common',
			'media-common',
			'microdata',
			'no-authors',
			'no-blocks',
			'no-comments',
			'search-common',
		);
		$this->load_helpers($array);
		$this->load_plugins();
		parent::autoload();
	}
}
