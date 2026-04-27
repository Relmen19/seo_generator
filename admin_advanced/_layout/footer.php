<?php
/**
 * Closes layout opened by header.php.
 * Optional $extraFoot — raw HTML before </body>.
 */
declare(strict_types=1);

$extraFoot = $extraFoot ?? '';
$assetVer  = $assetVer ?? '1';
?>
      </main>
    </div>
  </div>
</div>

<div id="seo-toast-host" class="fixed top-4 right-4 z-[1000] flex flex-col gap-2 pointer-events-none"></div>

<script src="/admin_advanced/_assets/js/app.js?v=<?= $assetVer ?>"></script>
<?= $extraFoot ?>
</body>
</html>
