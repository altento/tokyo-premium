<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pdo = db();
$variant = strtolower(trim((string)($_GET['variant'] ?? 'signature')));
$q = $pdo->prepare('SELECT * FROM custom_set_variants WHERE slug=? AND active=1'); $q->execute([$variant]);
$selectedVariant = $q->fetch() ?: $pdo->query("SELECT * FROM custom_set_variants WHERE active=1 ORDER BY sort_order LIMIT 1")->fetch();
if (!$selectedVariant) $selectedVariant = ['slug'=>'signature','name'=>'Tokyo Signature','minimum_distinct_items'=>3,'maximum_distinct_items'=>5,'maximum_total_quantity'=>8,'demo_only'=>1];
$variantOptions = $pdo->query('SELECT slug,name,minimum_distinct_items,maximum_distinct_items,maximum_total_quantity FROM custom_set_variants WHERE active=1 ORDER BY sort_order')->fetchAll();
$catalog = dishes();
page_start('Конструктор сета — Tokyo Premium', 'Соберите японский сет из демонстрационного меню. Итоговую стоимость и состав перед заказом проверяет сервер.');
header_html('experience');
?>
<main class="tp20-page tp20-builder-page">
  <section class="tp20-page-intro" data-tp-reveal><span class="kicker">BUILD YOUR TOKYO SET · <?=e(strtoupper((string)$selectedVariant['slug']))?></span><h1>Соберите свой<br>ритм вкуса</h1><p>Выберите формат и составьте сет из каталога. Выбор блюда и отображаемая сумма — предварительные; сервер повторно проверит активность и цену.</p><span class="tp20-mark" aria-hidden="true">組</span></section>
  <section class="section tp20-variant-picker" aria-label="Формат сета"><div class="editorial-heading"><div class="kicker">Выберите формат</div><h2>Три характера вечера</h2></div><div class="tp20-variant-grid"><?php foreach ($variantOptions as $option): ?><a class="tp20-variant-card <?=$option['slug']===$selectedVariant['slug']?'is-current':''?>" href="set-builder.php?variant=<?=e(rawurlencode((string)$option['slug']))?>" <?=$option['slug']===$selectedVariant['slug']?'aria-current="page"':''?>><span class="kicker"><?=e(strtoupper((string)$option['slug']))?></span><strong><?=e($option['name'])?></strong><small><?= (int)$option['minimum_distinct_items']?>–<?= (int)$option['maximum_distinct_items']?> разных блюд · до <?= (int)$option['maximum_total_quantity']?> порций</small></a><?php endforeach ?></div></section>
  <section class="section tp20-builder-section">
    <div class="editorial-heading"><div class="kicker">Сезонный каталог</div><h2>Выберите блюда для сета</h2><p>Выберите от <?= (int)$selectedVariant['minimum_distinct_items']?> до <?= (int)$selectedVariant['maximum_distinct_items']?> разных позиций. Общее количество не выше <?= (int)$selectedVariant['maximum_total_quantity']?> порций.</p></div>
    <div class="tp-set-layout">
      <div class="tp-set-catalog" data-tp-set-builder data-set-type="<?=e($selectedVariant['slug'])?>" data-set-name="<?=e($selectedVariant['name'])?>" data-min-distinct="<?= (int)$selectedVariant['minimum_distinct_items']?>" data-max-distinct="<?= (int)$selectedVariant['maximum_distinct_items']?>" data-max-quantity="<?= (int)$selectedVariant['maximum_total_quantity']?>">
        <?php foreach ($catalog as $dish): ?>
          <article class="tp-set-dish-card" data-tp-set-dish data-dish-id="<?= (int)$dish['id']?>">
            <img src="<?=e($dish['image_url'])?>" alt="<?=e($dish['name'])?>" loading="lazy" decoding="async">
            <div><span class="kicker"><?=e($dish['category_name'])?> · <?=e($dish['weight'])?></span><h3><?=e($dish['name'])?></h3><p><?=e($dish['description'])?></p><div class="tp20-set-card-bottom"><strong><?=number_format((int)$dish['price'],0,'.',' ')?> ₸</strong><div class="tp-set-dish-controls"><button type="button" data-tp-set-step="-1" data-dish-id="<?= (int)$dish['id']?>" aria-label="Уменьшить <?=e($dish['name'])?>">−</button><output data-tp-set-qty data-dish-id="<?= (int)$dish['id']?>">0</output><button type="button" data-tp-set-step="1" data-dish-id="<?= (int)$dish['id']?>" aria-label="Добавить <?=e($dish['name'])?>">+</button></div></div></div>
          </article>
        <?php endforeach ?>
        <p class="form-status tp20-builder-status" data-tp-status role="status" aria-live="polite">Выберите формат и добавьте разные блюда. Цены будут перепроверены сервером.</p>
        <div class="tp20-builder-action"><button class="button" type="button" data-tp-set-add disabled>Добавить сет в корзину ↗</button><span>Точная цена и доступность подтвердятся при оформлении.</span></div>
      </div>
      <aside class="tp-set-selection"><span class="kicker">ВАША КОМПОЗИЦИЯ</span><h3><?=e($selectedVariant['name'])?></h3><div class="tp20-progress-track" data-tp-set-progress></div><p><span data-tp-set-count>0 / <?= (int)$selectedVariant['maximum_total_quantity']?></span> порций · <?= (int)$selectedVariant['minimum_distinct_items']?>–<?= (int)$selectedVariant['maximum_distinct_items']?> разных блюд</p><div data-tp-set-composition><p class="tp-set-empty">Сет пока пуст. Добавьте блюда, чтобы увидеть состав.</p></div><div class="tp20-set-total"><span>Ориентир</span><strong data-tp-set-total>0 ₸</strong></div><p class="tp20-fineprint">Предварительная сумма — сумма демонстрационных цен выбранных блюд. Сервер соберёт окончательное предложение сам.</p><a class="text-link" href="pages.php?page=cart">Перейти к корзине ↗</a></aside>
    </div>
  </section>
  <section class="tp20-bottom-actions section"><a href="menu.php"><span>01</span><strong>Открыть всё меню</strong><i>↗</i></a><a href="perfect-evening.php"><span>02</span><strong>Получить персональный подбор</strong><i>↗</i></a></section>
</main>
<?php footer_html(); ?>
