/**
 * Admin JS para Sincronización CRC x USD
 */
(function ($) {
  'use strict';

  // Simulador de conversión
  $('#nlk-sim-btn').on('click', function () {
    var crc = parseFloat($('#nlk-sim-crc').val());
    if (!crc || crc <= 0) {
      $('#nlk-sim-result').text('Ingrese un precio válido en colones.');
      return;
    }

    // Leer el TC manual del campo del form o del valor mostrado
    var tc = parseFloat($('#nlk_crc_usd_tipo_cambio_manual').val());
    if (!tc || tc <= 0) {
      $('#nlk-sim-result').text(
        'No hay tipo de cambio configurado. Defina uno arriba.'
      );
      return;
    }

    var usd = (crc / tc).toFixed(2);
    $('#nlk-sim-result').html(
      '₡' +
        crc.toLocaleString('es-CR', { minimumFractionDigits: 2 }) +
        ' ÷ ₡' +
        tc.toLocaleString('es-CR', { minimumFractionDigits: 2 }) +
        ' = <strong>$' +
        usd +
        ' USD</strong>'
    );
  });
})(jQuery);
