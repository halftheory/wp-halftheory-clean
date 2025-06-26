		</main>

		<footer id="footer" role="contentinfo">
			<?php
			if ( has_nav_menu('footer') ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => 'nav',
						'container_id'   => 'nav-footer',
						'container_class' => '',
						'menu_id'        => 'menu-footer',
						'menu_class'     => '',
						'depth'          => 1,
						'item_spacing' => is_development() ? 'preserve' : 'discard',
					)
				);
			}
			?>
		</footer>

	</div>
<?php wp_footer(); ?>
</body>
</html>
