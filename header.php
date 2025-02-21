<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head itemscope itemtype="<?php echo esc_attr(set_url_scheme('http://schema.org/WebPage')); ?>">
<meta charset="<?php bloginfo('charset'); ?>">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<link rel="profile" href="http://gmpg.org/xfn/11">
<?php if ( is_singular() && pings_open(get_queried_object()) ) : ?>
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">
<?php endif; ?>
<title><?php bloginfo('name'); ?><?php wp_title('-', true, ''); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<div id="page">

		<header id="header" role="banner">
			<div id="header-title" role="contentinfo">
				<a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
					<?php
					if ( has_custom_logo() ) {
						if ( $tmp = get_image_context('img', get_theme_mod('custom_logo'), 'medium', array( 'class' => 'logo' )) ) {
							echo wp_kses_post($tmp);
						}
					}
					?>
					<h1><?php bloginfo('name'); ?></h1>
				</a>
			</div>
			<?php
			if ( has_nav_menu('primary') ) {
				?>
				<nav id="nav-primary" role="menu" aria-label="<?php esc_attr_e('Primary Menu'); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'menu',
						'menu_class'     => '',
						'container'      => '',
						'depth'          => 2,
					)
				);
				?>
				</nav>
				<?php
			}
			?>
		</header>

		<main id="main" role="main">
