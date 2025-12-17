    </main>
    <?php if (isLoggedIn()): ?>
        </div>
    </div>
    <?php endif; ?>
    <footer class="footer">
        <p>&copy; 2025 Образовательная платформа.</p>
    </footer>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (sidebar) {
                const isOpen = sidebar.classList.contains('open');
                sidebar.classList.toggle('open');
                
                if (window.innerWidth <= 768 && toggle) {
                    if (isOpen) {
                        toggle.classList.remove('hidden');
                    } else {
                        toggle.classList.add('hidden');
                    }
                }
            }
        }
        
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth > 768) {
                if (sidebar) {
                    sidebar.classList.remove('open');
                }
                if (toggle) {
                    toggle.classList.remove('hidden');
                }
            } else {
                    if (sidebar && !sidebar.classList.contains('open') && toggle) {
                    toggle.classList.remove('hidden');
                }
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && sidebar && toggle) {
                if (sidebar.classList.contains('open')) {
                    toggle.classList.add('hidden');
                }
            }
        });
    </script>
</body>
</html>

