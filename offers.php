<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pdo = db();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$offers = commerce_public_offers($pdo, $userId);
$collections = commerce_public_collections($pdo);
$codeRows = $pdo->query("SELECT pc.code,pc.campaign_id,pc.active AS code_active,pc.starts_at AS code_starts_at,pc.ends_at AS code_ends_at,pc.uses_count,pc.maximum_uses,c.title,c.active,c.starts_at,c.ends_at,c.weekday_mask,c.starts_time,c.ends_time,c.timezone FROM promo_codes pc JOIN promotion_campaigns c ON c.id=pc.campaign_id WHERE pc.active=1 AND c.active=1 AND c.campaign_type<>'secret_menu' ORDER BY c.sort_order,pc.created_at DESC")->fetchAll();
$codes = array_values(array_filter($codeRows, static fn(array $row): bool => commerce_campaign_is_active($row) && commerce_date_window_is_active($row['code_starts_at'] ?: null, $row['code_ends_at'] ?: null, (string)$row['timezone']) && ($row['maximum_uses'] === null || (int)$row['uses_count'] < (int)$row['maximum_uses'])));
$secretDishes = commerce_secret_menu_dishes($pdo, $userId, (string)($_SESSION['secret_menu_code']??''), !empty($_SESSION['admin_id']));
page_start('Привилегии и коллекции — Tokyo Premium', 'Сезонные предложения, меню шефа и привилегии Tokyo Premium. Учебные условия демонстрационные.');
header_html('offers');
?>
<main class="tp20-page tp20-offers-page">
  <section class="tp20-hero" data-tp-reveal>
    <img src="assets/generated/restaurant-interior.webp" alt="Тёплый свет и сезонная сервировка в вымышленной концепции Tokyo Premium" fetchpriority="high">
    <div class="tp20-hero-copy"><span class="kicker">TOKYO PREMIUM · СЕЗОННЫЕ ПРИВИЛЕГИИ</span><p class="tp20-overline">季節 · 特別なひととき</p><h1>Особый вечер<br>начинается с детали</h1><p>Камерные предложения, сезонные коллекции и выбор шефа. Все условия на этой учебной версии демонстрационные.</p><a class="button" href="#offers">Открыть привилегии <span aria-hidden="true">↘</span></a></div>
    <div class="tp20-hero-index" aria-hidden="true">TP / 02</div>
  </section>
  <section class="tp20-proofline" aria-label="Условия демонстрационного режима"><span>ДЕМО-УСЛОВИЯ</span><span>Без реальной оплаты</span><span>Тенге · учебные цены</span><span>Часовой пояс ресторана: Asia/Qyzylorda</span></section>
  <section class="section tp20-section" id="offers">
    <div class="editorial-heading"><div class="kicker">Tokyo Exclusive</div><h2>Предложения сезона</h2><p>Серверная версия показывает только активные кампании. В публичной статической версии эти карточки остаются демонстрационным контентом.</p></div>
    <?php if (!$offers): ?>
      <div class="tp20-empty"><span lang="ja" aria-hidden="true">余白</span><div><h3>Сейчас — время для выбора</h3><p>Активных кампаний пока нет. Новые предложения появятся здесь после настройки в панели управления.</p><a class="text-link" href="admin-marketing.php">Управлять предложениями ↗</a></div></div>
    <?php else: ?>
      <div class="tp20-offer-grid">
        <?php foreach ($offers as $index => $offer): ?>
          <article class="tp20-offer-card" data-tp-reveal>
            <div class="tp20-offer-image"><img src="<?=e($offer['image_url'] ?: 'assets/generated/room-main-hall.webp')?>" alt="<?=e($offer['title'])?> · учебная визуализация" loading="lazy" decoding="async"><span><?=sprintf('%02d', $index + 1)?></span></div>
            <div class="tp20-offer-copy"><span class="kicker"><?=e(match($offer['type']) {'golden_hour'=>'GOLDEN HOUR','chef_drop'=>'CHEF’S SECRET DROP','birthday'=>'BIRTHDAY PRIVILEGE','personal'=>'PERSONAL INVITATION',default=>'TOKYO EXCLUSIVE'})?></span><h3><?=e($offer['title'])?></h3><p><?=e($offer['description'])?></p><div class="tp20-offer-meta"><span><?=!empty($offer['demo_only'])?'Учебное предложение':'Предложение ресторана'?></span><?php if (!empty($offer['ends_at'])): ?><span>до <?=e(substr((string)$offer['ends_at'],0,10))?></span><?php endif ?></div>
              <?php if (in_array($offer['reward_type'], ['percent','fixed'], true) && (int)$offer['reward_value'] > 0): ?><strong class="tp20-reward"><?= $offer['reward_type']==='percent' ? (int)$offer['reward_value'].'%' : number_format((int)$offer['reward_value'],0,'.',' ').' ₸'?> <small>по условиям акции</small></strong><?php endif ?>
              <a class="text-link" href="menu.php">Выбрать блюда ↗</a></div>
          </article>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </section>
  <?php if ($codes): ?><section class="tp20-code-vault section" aria-labelledby="tp20-code-title"><div><span class="kicker">PRIVATE INVITATIONS</span><h2 id="tp20-code-title">Коды предложений</h2><p>Применимость каждого кода и итоговая стоимость проверяются сервером при расчёте заказа.</p></div><div class="tp20-code-list"><?php foreach ($codes as $promo): ?><article><span><?=e($promo['title'])?></span><strong><?=e($promo['code'])?></strong><button class="button secondary" type="button" data-copy-code="<?=e($promo['code'])?>" data-label="Скопировать код">Скопировать код</button></article><?php endforeach ?></div></section><?php endif ?>
  <section class="tp20-feature-band" data-tp-reveal><div class="tp20-feature-art"><img src="assets/generated/tokyo-seasonal-omakase-v2.webp" alt="Осенняя дегустационная подача Tokyo Premium, авторское изображение" loading="lazy" decoding="async"></div><div class="tp20-feature-copy"><span class="kicker">CHEF’S SECRET DROP</span><h2>Один момент.<br>Одна авторская подача.</h2><p>Предложения шефа появляются только при активной кампании. Без фиктивного счётчика порций и обещаний наличия.</p><a class="button secondary" href="#offers">Посмотреть сезонный выбор ↗</a></div></section>
  <section class="section tp20-section">
    <div class="editorial-heading"><div class="kicker">Seasonal stories</div><h2>Коллекции Tokyo</h2><p>Собранные по сезону сочетания — от меню до пространства вечера.</p></div>
    <?php if ($collections): ?><div class="tp20-collection-grid">
      <?php foreach ($collections as $collection): ?><a class="tp20-collection-card" href="collection.php?slug=<?=e(rawurlencode((string)$collection['slug']))?>" data-theme="<?=e($collection['theme_key'])?>"><img src="<?=e($collection['image_url'] ?: 'assets/generated/room-garden-terrace.webp')?>" alt="<?=e($collection['name'])?> · сезонная коллекция, учебная концепция" loading="lazy" decoding="async"><span class="kicker"><?=e(strtoupper((string)$collection['theme_key']))?> · <?= (int)$collection['dish_count']?> блюд</span><strong><?=e($collection['name'])?></strong><span><?=e($collection['description'])?></span><i aria-hidden="true">↗</i></a><?php endforeach ?>
    </div><?php else: ?><p class="tp20-note">Коллекции появятся после добавления и активации в админке.</p><?php endif ?>
  </section>
  <section class="section tp20-secret-section" id="secret-menu">
    <div class="tp20-secret-intro"><span class="kicker">裏 · SECRET MENU</span><h2>За границей привычного меню</h2><p>В PHP-версии доступ определяется серверными правилами и уровнем аккаунта. Статическая демоверсия не предоставляет серверную авторизацию.</p></div>
    <?php if ($secretDishes): ?><div class="dish-grid tp20-secret-grid"><?php foreach ($secretDishes as $dish): ?><article class="dish-card"><div class="dish-image-wrap"><img src="<?=e($dish['image_url'])?>" alt="<?=e($dish['name'])?>" loading="lazy"></div><div class="dish-meta"><small>SECRET MENU · учебная позиция</small><h3><?=e($dish['name'])?></h3><p><?=e($dish['description'])?></p><div class="dish-bottom"><span class="dish-price"><?=number_format((int)$dish['price'],0,'.',' ')?> ₸</span><button class="add-to-cart" type="button" data-id="<?= (int)$dish['id']?>">Добавить +</button></div></div></article><?php endforeach ?></div>
    <?php else: ?><form class="tp20-secret-form" data-tp-secret-unlock><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label for="secret-code">Код доступа</label><div class="tp20-inline-form"><input id="secret-code" name="code" autocomplete="one-time-code" maxlength="40" placeholder="Введите приглашение" required><button class="button" type="submit">Проверить код</button></div><p class="form-status" data-tp-secret-status aria-live="polite">Сервер проверит приглашение, роль или уровень привилегий.</p><div data-tp-secret-results hidden></div></form><?php endif ?>
  </section>
  <section class="tp20-privilege-cta"><div><span class="kicker">TOKYO PRIVILEGE</span><h2>Память о каждом вечере</h2><p>История заказов, персональные настройки и демонстрационная карта участника — в вашем профиле.</p></div><a class="button" href="account.php">Перейти в профиль ↗</a></section>
  <section class="tp20-bottom-actions section"><a href="set-builder.php"><span>01</span><strong>Собрать собственный сет</strong><i>↗</i></a><a href="perfect-evening.php"><span>02</span><strong>Найти идеальный вечер</strong><i>↗</i></a><a href="gift-certificates.php"><span>03</span><strong>Создать дизайн сертификата</strong><i>↗</i></a></section>
</main>
<?php footer_html(); ?>
