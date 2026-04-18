</div> <!-- /.container -->

  <!-- Bootstrap JS local (incluye Popper) -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>

  <script>
  (function(){
    // Páginas donde quiero auto-reload en 40s (mantenemos autorizar_compra)
    const page = window.location.pathname.split('/').pop();
    if (![
      'autorizar_ajustes.php',
      'autorizar_produccion.php',
      'autorizar_compra.php'
    ].includes(page)) {
      return;
    }

    let reloadTimer;

    // Función para (re)iniciar el timer
    function scheduleReload() {
      clearTimeout(reloadTimer);
      reloadTimer = setTimeout(() => {
        window.location.reload();
      }, 40000); // 40 segundos
    }

    // Arranca el timer cuando carga la página
    window.addEventListener('load', scheduleReload);

    // Cancela reload al enfocar un campo de formulario
    document.querySelectorAll('input, textarea, select').forEach(el => {
      el.addEventListener('focus', () => clearTimeout(reloadTimer));
      el.addEventListener('blur',  scheduleReload);
    });
  })();
  </script>

</body>
</html>