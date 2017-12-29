<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head itemscope itemtype="http://schema.org/Webpage">
<meta charset="<?php bloginfo('charset'); ?>">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<link rel="profile" href="http://gmpg.org/xfn/11">
<?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">
<?php endif; ?>
<title><?php wp_title('-', true, ''); ?></title>
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div id="page">

		<header id="header" class="clear">

			<div id="logo" class="clear">
				<div class="float-left">
					<h1 class="site-title"><a href="<?php echo esc_url( home_url('/') ); ?>" rel="home"><?php bloginfo('name'); ?></a></h1>
				</div>
				<div class="float-right">
					<p class="site-description"><?php bloginfo('description', 'display'); ?></p>
				</div>
			</div>

			<?php
				if (has_nav_menu('primary-menu')) : ?><nav id="navigation" class="clear" aria-label="Primary Menu">
				<?php
					wp_nav_menu( array(
						'theme_location' 	=> 'primary-menu',
						'menu_id' 			=> 'menu',
						'menu_class' 		=> '',
						'container' 		=> '',
						'depth' 			=> 2,
					 ) );
				?>
			</nav><?php endif; ?>

		</header>

		<main id="main" class="clear">
