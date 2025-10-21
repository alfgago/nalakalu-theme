<?php

/**
 * The template for displaying the footer
 *
 * @package nalakalu-2025
 */

$novedades = get_field('novedades', 'option');
$social_links = get_field('social_links', 'option');
$subscribe = get_field('subscribe', 'option');
?>

<footer id="colophon" class="site-footer bg-cafe text-white py-12 md:py-16">
	<div class="container-nalakalu">

		<div class="footer-top border-b border-beige pb-12 mb-12">
			<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-12">

				<div class="lg:col-span-3">
					<?php if ($novedades): ?>
						<p class="font-body-small text-white mb-6">
							<?php echo esc_html($novedades); ?>
						</p>
					<?php endif; ?>

					<?php if ($social_links && is_array($social_links)): ?>
						<div class="flex gap-4">
							<?php foreach ($social_links as $social):
								$icon = isset($social['icon']) ? $social['icon'] : '';
								$url = isset($social['url']) ? $social['url'] : '#';
							?>
								<a
									href="<?php echo esc_url($url); ?>"
									target="_blank"
									rel="noopener noreferrer"
									class="text-white hover:text-beige transition-default"
									aria-label="<?php echo esc_attr($icon); ?>">
									<?php if ($icon): ?>
										<span class="font-body-small"><?php echo esc_html($icon); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="lg:col-span-6">
					<?php
					if (has_nav_menu('menu-1')) {
						$menu_items = wp_get_nav_menu_items(get_nav_menu_locations()['menu-1']);

						if ($menu_items) {
							$menu_columns = array_chunk($menu_items, ceil(count($menu_items) / 3));
					?>
							<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
								<?php foreach ($menu_columns as $column): ?>
									<div class="footer-menu-column">
										<nav>
											<ul class="space-y-3">
												<?php foreach ($column as $item): ?>
													<li>
														<a
															href="<?php echo esc_url($item->url); ?>"
															class="font-body-small text-white hover:text-beige transition-default">
															<?php echo esc_html($item->title); ?>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>
										</nav>
									</div>
								<?php endforeach; ?>
							</div>
					<?php
						}
					}
					?>
				</div>

				<div class="lg:col-span-3">
					<?php if ($subscribe): ?>
						<h3 class="font-heading-5 text-white mb-6">
							<?php echo esc_html($subscribe); ?>
						</h3>
					<?php endif; ?>

					<form class="footer-newsletter-form" action="#" method="post">
						<div class="flex flex-col sm:flex-row gap-3">
							<input
								type="email"
								name="email"
								placeholder="Correo Electrónico"
								required
								class="flex-1 px-4 py-3 bg-transparent border border-white rounded font-body-small text-white placeholder:text-white placeholder:opacity-60 focus:outline-none focus:border-beige transition-default">
							<button
								type="submit"
								class="btn btn-blanco whitespace-nowrap">
								Enviar
							</button>
						</div>
					</form>
				</div>

			</div>
		</div>

		<div class="footer-logo flex justify-center items-center py-12 border-b border-beige">
			<div class="footer-logo-wrapper max-w-4xl w-full">
				<?php
				$footer_logo = get_field('footer_logo', 'option');
				if ($footer_logo): ?>
					<img
						src="<?php echo esc_url($footer_logo['url']); ?>"
						alt="<?php echo esc_attr($footer_logo['alt'] ?: 'Nalakalu'); ?>"
						class="w-full h-auto">
				<?php endif; ?>
			</div>
		</div>

		<div class="footer-bottom pt-8">
			<p class="font-caption-small text-white text-center opacity-60">
				© <?php echo date('Y'); ?> Todos los derechos reservados
			</p>
		</div>

	</div>
</footer>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>

</html>