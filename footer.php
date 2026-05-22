<?php
/**
 * Footer Na Lakalú
 * Incluir con get_footer()
 */
?>

<footer>
  <div class="footer-main">
    <div class="footer-column">
      <h3 class="font-body-small">Na lakalú</h3>
      <?php
      wp_nav_menu([
        'theme_location' => 'footer_nalakalu',
        'container'      => false,
        'menu_class'     => '',
        'fallback_cb'    => function () {
          echo '<ul><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">Configurar menú "Footer: Na lakalú"</a></li></ul>';
        },
      ]);
      ?>
    </div>

    <div class="footer-column">
      <h3 class="font-body-small">Showrooms</h3>
      <?php
      wp_nav_menu([
        'theme_location' => 'footer_showrooms',
        'container'      => false,
        'menu_class'     => '',
        'fallback_cb'    => function () {
          echo '<ul><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">Configurar menú "Footer: Showrooms"</a></li></ul>';
        },
      ]);
      ?>
    </div>

    <div class="footer-column">
      <h3 class="font-body-small">Productos</h3>
      <?php
      wp_nav_menu([
        'theme_location' => 'footer_productos',
        'container'      => false,
        'menu_class'     => '',
        'fallback_cb'    => function () {
          echo '<ul><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">Configurar menú "Footer: Productos"</a></li></ul>';
        },
      ]);
      ?>
    </div>

    <div class="font-body-small footer-column">
      <h3 class="font-body-small">Más</h3>
      <?php
      wp_nav_menu([
        'theme_location' => 'footer_mas',
        'container'      => false,
        'menu_class'     => '',
        'fallback_cb'    => function () {
          echo '<ul><li><a href="' . esc_url(admin_url('nav-menus.php')) . '">Configurar menú "Footer: Más"</a></li></ul>';
        },
      ]);
      ?>
    </div>
  </div>

  <div class="footer-newsletter">
    <div class="newsletter-text desktop-only"><h3 class="font-heading-4"><?php echo esc_html( get_theme_mod('nlk_news_title', 'Recibí inspiración y novedades directamente en tu correo.') ); ?></h3>
      <div class="social-icons desktop-only">
  <?php if (function_exists('nlk_render_social_icons')) nlk_render_social_icons(); ?>
</div>

    </div>

    <div class="newsletter-form">
        
      <h3 class="font-heading-4"><?php echo esc_html( get_theme_mod('nlk_news_form_title', 'Suscríbete') ); ?></h3>
      <form class="form-group" action="#" method="post">
        <input type="email" name="email"
  placeholder="<?php echo esc_attr( get_theme_mod('nlk_news_placeholder', 'Correo Electrónico') ); ?>" required>

        <button type="submit">Enviar</button>
      </form>
    </div>
  </div>
<div class="footer-social footer-newsletter">
    <div class="newsletter-text"><h3 class="font-heading-4"><?php echo esc_html( get_theme_mod('nlk_news_title', 'Recibí inspiración y novedades directamente en tu correo.') ); ?></h3></div>
      <div class="social-icons">
  <?php if (function_exists('nlk_render_social_icons')) nlk_render_social_icons(); ?>
</div>
</div>
  <div class="footer-logo">
   <div class="site-branding logo-footer">
            <?php if (has_custom_logo()) { the_custom_logo(); } ?>
          </div>
  </div>

  <div class="footer-bottom">
<?php
    $legal = get_theme_mod('nlk_footer_legal');
    echo $legal ? wp_kses_post($legal) : '© ' . esc_html( date_i18n('Y') ) . ' ' . esc_html__('Todos los derechos reservados','nalakalu');
  ?>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

