        </div>
        <!-- /container -->
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script>if (window.hljs) hljs.highlightAll();</script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/theme.js"></script>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/chat.css">
    <script src="<?php echo ASSETS_URL; ?>/js/chat.js"></script>
    <script>
    (function(){
      function enhance(){
        var blocks = document.querySelectorAll('pre code');
        blocks.forEach(function(code){
          var pre = code.parentElement;
          if (!pre || (pre.nextElementSibling && pre.nextElementSibling.classList && pre.nextElementSibling.classList.contains('run-sample-btn'))) return;
          var btn = document.createElement('button');
          btn.className = 'run-sample-btn';
          btn.type = 'button';
          btn.textContent = 'Ishga tushirish';
          btn.addEventListener('click', function(){
            try { sessionStorage.setItem('codestep.pending-code', code.textContent); sessionStorage.setItem('codestep.pending-lang', (code.className||'').replace('language-','')||'cpp'); } catch(e){}
            window.location.href = '?page=code-editor';
          });
          pre.parentNode.insertBefore(btn, pre.nextSibling);
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance); else enhance();
    })();
    </script>
    
    <?php if (isset($pageScript)): ?>
    <script src="<?php echo ASSETS_URL; ?>/js/<?php echo $pageScript; ?>"></script>
    <?php endif; ?>
    
    <!-- Splash screen animation -->
    <?php if (!isset($_COOKIE['visited'])): ?>
    <script>
        setTimeout(() => {
            document.getElementById('splash')?.classList.add('hide');
        }, 2500);
    </script>
    <?php endif; ?>
</body>
</html>