		</main>

		<footer id="footer" class="clear">
		<?php if ( is_active_sidebar( 'sidebar-2' )  ) : ?>
			<aside id="sidebar-2" class="sidebar widget-area">
				<?php dynamic_sidebar( 'sidebar-2' ); ?>
			</aside><!-- #sidebar-2 -->
		<?php endif; ?>

		<?php if ( is_active_sidebar( 'sidebar-3' )  ) : ?>
			<aside id="sidebar-3" class="sidebar widget-area">
				<?php dynamic_sidebar( 'sidebar-3' ); ?>
			</aside><!-- #sidebar-3 -->
		<?php endif; ?>
		</footer>

	</div><!-- #page -->
<?php wp_footer(); ?>
</body>
</html>
