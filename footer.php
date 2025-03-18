		</main>

		<footer id="footer" role="contentinfo">
			<?php
			if ( has_nav_menu('footer') ) {
				?>
				<nav id="nav-footer" role="menu" aria-label="<?php esc_attr_e('Footer Menu'); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'menu_id'        => 'menu-footer',
						'menu_class'     => '',
						'container'      => '',
						'depth'          => 1,
						'item_spacing' => is_development() ? 'preserve' : 'discard',
					)
				);
				?>
				</nav>
				<?php
			}
			?>
		</footer>

	</div>
<?php wp_footer(); ?>
</body>
</html>
