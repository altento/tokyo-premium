<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$query = db()->prepare('SELECT * FROM seasonal_collections WHERE slug=? AND active=1');
$query->execute([$slug]);
$collection = $query->fetch();
if (!$collection) { http_response_code(404); $collection = null; $items = []; }
else { $itemsQuery = db()->prepare('SELECT d.*,c.name AS category_name FROM seasonal_collection_items ci JOIN dishes d ON d.id=ci.dish_id JOIN categories c ON c.slug=d.category WHERE ci.collection_id=? AND d.active=1 ORDER BY ci.sort_order,d.sort_order'); $itemsQuery->execute([$collection['id']]); $items=commerce_filter_secret_menu_dishes(db(),$itemsQuery->fetchAll(),isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,(string)($_SESSION['secret_menu_code']??''),!empty($_SESSION['admin_id'])); }
page_start(($collection['name'] ?? 'Коллекция не найдена') . ' — Tokyo Premium', $collection ? (string)$collection['description'] : 'Запрошенная сезонная коллекция недоступна.');
header_html('offers');
?>
<main class="tp20-page tp20-collection-page">
  <?php if (!$collection): ?><section class="tp20-page-intro"><span class="kicker">TOKYO COLLECTIONS</span><h1>Коллекция<br>недоступна</h1><p>Коллекция не найдена или её срок завершён. Список активных предложений доступен на странице привилегий.</p><a class="button" href="offers.php">К привилегиям ↗</a></section>
  <?php else: ?>
    <section class="tp20-collection-hero" data-theme="<?=e($collection['theme_key'])?>"><img src="<?=e($collection['image_url'] ?: 'assets/generated/tokyo-seasonal-omakase-v2.webp')?>" alt="<?=e($collection['name'])?> — сезонный визуал учебной концепции" fetchpriority="high"><div><span class="kicker">SEASONAL COLLECTION · <?=e(strtoupper((string)$collection['theme_key']))?></span><h1><?=e($collection['name'])?></h1><p><?=e($collection['description'])?></p><a class="button" href="#collection-dishes">Открыть коллекцию ↘</a></div></section>
    <section class="section tp20-section" id="collection-dishes"><div class="editorial-heading"><div class="kicker">Состав коллекции</div><h2>Сезонные позиции</h2><p>Позиции и цены относятся к учебной базе меню.</p></div>
    <?php if (!$items): ?><div class="tp20-empty"><div><h3>Состав коллекции обновляется</h3><p>Добавьте активные блюда через панель управления, чтобы наполнить коллекцию.</p></div></div><?php else: ?><div class="dish-grid"><?php foreach ($items as $dish): ?><article class="dish-card"><div class="dish-image-wrap"><img src="<?=e($dish['image_url'])?>" alt="<?=e($dish['name'])?>" loading="lazy" decoding="async"></div><div class="dish-meta"><small><?=e($dish['category_name'])?> · <?=e($dish['weight'])?></small><button class="favorite-btn" type="button" data-favorite="<?= (int)$dish['id']?>" aria-label="Сохранить в избранное">♡</button><h3><a href="pages.php?page=dish&amp;id=<?= (int)$dish['id']?>"><?=e($dish['name'])?></a></h3><p><?=e($dish['description'])?></p><div class="dish-bottom"><span class="dish-price"><?=number_format((int)$dish['price'],0,'.',' ')?> ₸</span><button class="add-to-cart" type="button" data-id="<?= (int)$dish['id']?>">Добавить +</button></div></div></article><?php endforeach ?></div><?php endif ?>
    </section>
    <section class="tp20-bottom-actions section"><a href="offers.php"><span>01</span><strong>Все привилегии</strong><i>↗</i></a><a href="pages.php?page=booking"><span>02</span><strong>Планировать вечер</strong><i>↗</i></a></section>
  <?php endif ?>
</main>
<?php footer_html(); ?>
