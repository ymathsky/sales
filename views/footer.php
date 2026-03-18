                </div>
            </main>
        </div>
    </div>
    <?php if (isLoggedIn()): ?>
    <?php $v = '1.0'; // Static version to prevent unnecessary reloading ?>
    <script src="/sales/assets/js/notifications.js?v=<?= $v ?>"></script>
    <script src="/sales/assets/js/main.js?v=<?= $v ?>"></script>
    <script src="/sales/assets/js/company-switcher.js?v=<?= $v ?>"></script>
    
    <?php if (hasFlashMessage()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            $flash = getFlashMessage();
            $type = $flash['type'] ?? 'info';
            $message = addslashes($flash['message']);
            ?>
            showNotification('<?= $message ?>', '<?= $type ?>', 4000);
        });
    </script>
    <?php endif; ?>
    <?php endif; ?>
</body>
</html>
