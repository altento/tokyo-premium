<?php
require __DIR__.'/bootstrap.php';
page_start('Меню — Tokyo Premium','Японское меню Tokyo Premium в Астане: закуски, суши, сеты омакасе, горячие блюда и десерты.');
header_html('menu');
$all=dishes();
$searchMetadata=[];
$metadataQuery=db()->query("SELECT d.id, COALESCE(GROUP_CONCAT(DISTINCT i.name), '') AS ingredients, COALESCE((SELECT GROUP_CONCAT(t.tag, ' ') FROM dish_tags t WHERE t.dish_id=d.id), '') AS tags, COALESCE((SELECT GROUP_CONCAT(p.name, ' ') FROM dish_pairings dp JOIN dishes p ON p.id=dp.paired_dish_id AND p.active=1 WHERE dp.dish_id=d.id), '') AS pairings FROM dishes d LEFT JOIN dish_ingredients di ON di.dish_id=d.id LEFT JOIN ingredients i ON i.slug=di.ingredient_slug AND i.active=1 WHERE d.active=1 GROUP BY d.id");
foreach($metadataQuery->fetchAll() as $row) $searchMetadata[(int)$row['id']]=strtolower(implode(' ',[$row['ingredients'],$row['tags'],$row['pairings']]));
$cats=db()->query('SELECT slug,name FROM categories ORDER BY sort_order')->fetchAll();
?>
<main class="menu-page">
  <section class="menu-hero"><img src="assets/generated/home-hero-nigiri-v2.webp" alt="Нигири и суши-сет из вымышленного меню Tokyo Premium" fetchpriority="high"><div class="menu-hero-shade"></div><div class="menu-hero-copy"><div class="kicker">旬 · СЕЗОННЫЙ ВЫБОР</div><h1>Меню</h1><p class="menu-hero-sub">Коллекция вкусов Японии</p><p>Авторские блюда концепции — от чистого вкуса нигири до тёплых подач и сезонных десертов.</p><span class="school-note">Все блюда и цены — демонстрационные</span></div><span class="menu-hero-kanji" lang="ja" aria-hidden="true">日本の味</span></section>
  <section class="section menu-section">
    <div class="menu-layout">
      <aside class="menu-sidebar" aria-label="Разделы меню">
        <div class="menu-sidebar-heading"><span class="kicker">Выберите настроение</span><h2>Категории</h2></div>
        <nav class="menu-category-list" aria-label="Фильтр по категории">
          <button class="filter-btn active" data-category="all"><span>Все блюда</span><b><?=count($all)?></b></button>
          <?php foreach($cats as $c): $categoryCount=count(array_filter($all,static fn($d)=>$d['category']===$c['slug'])); ?><button class="filter-btn" data-category="<?=e($c['slug'])?>"><span><?=e($c['name'])?></span><b><?=$categoryCount?></b></button><?php endforeach ?>
        </nav>
        <article class="menu-tea-card"><img src="assets/food/04-matcha-latte.webp" alt="Матча латте из демонстрационного меню" loading="lazy" decoding="async"><div><span class="kicker">Чайная пауза</span><h3>Японский чай</h3><p>Найдите спокойный финал вечера в разделе напитков.</p><a href="#menu-catalog" data-category-link="drinks">К напиткам ↗</a></div></article>
      </aside>
      <div class="menu-catalog" id="menu-catalog">
        <div class="menu-catalog-heading"><div><span class="kicker">Tokyo Premium · учебный каталог</span><h2>Вкусы на ваш выбор</h2></div><button type="button" class="filter-btn" id="favorites-only">♡ Избранное</button></div>
        <div class="menu-toolbar"><label class="menu-sort"><span>Сортировка</span><select id="sort-menu" aria-label="Сортировка меню"><option value="default">Сначала по разделам</option><option value="price-asc">Цена: по возрастанию</option><option value="price-desc">Цена: по убыванию</option><option value="name">Название: А–Я</option></select></label><label class="menu-search-wrap"><span class="sr-only">Поиск блюд</span><input class="menu-search" type="search" aria-label="Поиск блюд" placeholder="⌕  Поиск по меню"></label></div>
        <div class="dish-grid">
          <?php foreach($all as $d): ?>
          <article class="dish-card reveal" data-category="<?=e($d['category'])?>" data-search="<?=e(strtolower($d['name'].' '.$d['description'].' '.$d['category_name'].' '.($searchMetadata[(int)$d['id']]??'')))?>">
            <div class="dish-image-wrap"><img src="<?=e($d['image_url'])?>" srcset="<?=e(image_srcset($d['image_url']))?>" sizes="(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw" width="1200" height="800" alt="<?=e($d['name'])?>" loading="lazy" decoding="async"></div>
            <div class="dish-meta"><small><?=e($d['category_name'])?> · <?=e($d['weight'])?></small>
              <button class="favorite-btn" type="button" data-favorite="<?= (int)$d['id']?>" aria-label="Сохранить в избранное">♡</button>
              <h3><a href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>"><?=e($d['name'])?></a></h3><p><?=e($d['description'])?></p>
              <div class="dish-bottom"><a class="text-button" href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>">Подробнее ↗</a><button type="button" class="text-button" data-tp-quick-view data-dish-id="<?=(int)$d['id']?>">Быстрый просмотр</button><span class="dish-price"><?=number_format((int)$d['price'],0,'.',' ')?> ₸</span><button class="add-to-cart" type="button" data-id="<?= (int)$d['id']?>">Добавить +</button></div>
            </div>
          </article>
          <?php endforeach ?>
        </div>
        <nav class="menu-pagination" id="menu-pagination" aria-label="Страницы меню"></nav>
        <p class="empty-state" hidden>По вашему запросу блюд нет. Попробуйте изменить поиск.</p>
        <p class="menu-note">Все блюда, цены и гости — часть вымышленной ресторанной концепции для лабораторной работы.</p>
      </div>
    </div>
  </section>
</main>
<script>window.DISHES=<?=json_encode($all,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;</script>
<?php footer_html(); ?>
