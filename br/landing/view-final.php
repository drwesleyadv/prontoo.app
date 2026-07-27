    <section class="br-section br-section--compact" aria-labelledby="personalizacao-title">
      <div class="br-shell br-personalization">
        <div>
          <p class="br-eyebrow"><span></span>Seu ambiente</p>
          <h2 id="personalizacao-title">Um sistema que acompanha a forma como sua equipe trabalha.</h2>
          <p>O consultório pode personalizar nomes dos setores, cor de destaque e ícones, tornando o sistema mais reconhecível e coerente com sua organização.</p>
        </div>
        <div class="br-availability">
          <div class="br-device-stack" aria-hidden="true"><span><i class="material-symbols-rounded">desktop_windows</i>Web</span><span><i class="material-symbols-rounded">android</i>Android</span><span><i class="material-symbols-rounded">phone_iphone</i>iPhone</span></div>
          <div><strong>No computador, Android e iPhone.</strong><p>A rotina permanece acessível nos dispositivos em que o trabalho acontece.</p></div>
        </div>
      </div>
    </section>

    <section class="br-trial" aria-labelledby="trial-title">
      <div class="br-shell br-trial__box">
        <div class="br-trial__copy">
          <p class="br-eyebrow br-eyebrow--light"><span></span>Comece antes de decidir</p>
          <h2 id="trial-title">30 dias para organizar uma rotina real.</h2>
          <p>Crie seu consultório, conheça os recursos e experimente o Prontoo com sua equipe. O período gratuito não gera obrigação de continuidade.</p>
          <a class="br-btn br-btn--light" href="../index.php?r=signup">Criar consultório gratuitamente</a>
        </div>
        <div class="br-trial__facts" aria-label="Condições do período gratuito">
          <article><span class="material-symbols-rounded" aria-hidden="true">today</span><div><strong>30 dias gratuitos</strong><p>para novos consultórios</p></div></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">verified</span><div><strong>Recursos disponíveis</strong><p>para experimentar a rotina</p></div></article>
          <article><span class="material-symbols-rounded" aria-hidden="true">do_not_disturb_on</span><div><strong>Sem obrigação</strong><p>de continuidade</p></div></article>
        </div>
      </div>
    </section>

    <section class="br-section br-section--faq" id="perguntas" aria-labelledby="faq-title">
      <div class="br-shell">
        <div class="br-section__head">
          <p class="br-eyebrow"><span></span>Perguntas frequentes</p>
          <h2 id="faq-title">O essencial para conhecer o Prontoo.</h2>
        </div>
        <div class="br-faq">
          <?php foreach ($brLandingFaq as $faqItem): ?>
            <details class="br-faq__item">
              <summary><?php echo br_h($faqItem["q"]); ?></summary>
              <p><?php echo br_h($faqItem["a"]); ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="br-final" aria-labelledby="final-title">
      <div class="br-shell">
        <div class="br-final__card">
          <div class="br-final__inner">
            <div>
              <p class="br-eyebrow br-eyebrow--light"><span></span>Prontoo</p>
              <h2 id="final-title">O cuidado precisa de rotina. A rotina precisa de clareza.</h2>
              <p>Reúna pacientes, agenda, documentos e equipe em um fluxo simples de acompanhar.</p>
              <div class="br-final__tags" aria-label="Condições do cadastro"><span>30 dias gratuitos</span><span>Recursos disponíveis</span><span>Sem obrigação de continuidade</span></div>
            </div>
            <a class="br-btn br-btn--light" href="../index.php?r=signup">Criar consultório gratuitamente</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="br-footer">
    <div class="br-shell br-footer__inner">
      <a class="br-brand br-brand--footer" href="#inicio" aria-label="Prontoo — voltar ao início">
        <img src="../public/assets/prontoo-mark-<?php echo br_h($brLandingAssetRevision); ?>.png" alt="" class="br-brand__mark" width="32" height="32">
        <span class="br-brand__name">Prontoo</span>
      </a>
      <p>Organize o consultório. Mantenha o cuidado no centro.</p>
    </div>
  </footer>
</body>
</html>
