<?php
require __DIR__.'/bootstrap.php';
page_start('Меню — Tokyo Premium','Японское меню Tokyo Premium в Астане: закуски, суши, сеты омакасе, горячие блюда и десерты.');
header_html('menu');
$all=dishes();
$cats=db()->query('SELECT slug,name FROM categories ORDER BY sort_order')->fetchAll();
?>
<main>
  <section class="page-hero">
    <div class="kicker">Точность вкуса · свобода выбора</div>
    <h1>Меню для долгого вечера</h1>
    <p>От лёгкой закуски до сета омакасе — соберите свой вечер из вкусов, текстур и температуры.</p>
  </section>
  <section class="section">
    <div class="filter-row">
      <button class="filter-btn active" data-category="all">Всё меню</button>
      <?php foreach($cats as $c): ?><button class="filter-btn" data-category="<?=e($c['slug'])?>"><?=e($c['name'])?></button><?php endforeach ?>
      <select class="menu-search" id="sort-menu" aria-label="Сортировка меню">
        <option value="default">Сначала по разделам</option><option value="price-asc">Цена: по возрастанию</option>
        <option value="price-desc">Цена: по убыванию</option><option value="name">Название: А–Я</option>
      </select>
      <button type="button" class="filter-btn" id="favorites-only">Избранное</button>
      <input class="menu-search" type="search" aria-label="Поиск блюд" placeholder="Найти блюдо">
    </div>
    <div class="dish-grid">
      <?php foreach($all as $d): ?>
      <article class="dish-card reveal" data-category="<?=e($d['category'])?>" data-search="<?=e(strtolower($d['name'].' '.$d['description'].' '.$d['category_name']))?>">
        <div class="dish-image-wrap"><img src="<?=e($d['image_url'])?>" srcset="<?=e(image_srcset($d['image_url']))?>" sizes="(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw" width="1200" height="800" alt="<?=e($d['name'])?>" loading="lazy" decoding="async"></div>
        <div class="dish-meta"><small><?=e($d['category_name'])?> · <?=e($d['weight'])?></small>
          <button class="favorite-btn" type="button" data-favorite="<?= (int)$d['id']?>" aria-label="Сохранить в избранное">♡</button>
          <h3><a href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>"><?=e($d['name'])?></a></h3><p><?=e($d['description'])?></p>
          <div class="dish-bottom"><a class="text-button" href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>">Подробнее ↗</a><span class="dish-price"><?=number_format((int)$d['price'],0,'.',' ')?> ₸</span><button class="add-to-cart" type="button" data-id="<?= (int)$d['id']?>">Добавить +</button></div>
        </div>
      </article>
      <?php endforeach ?>
    </div>
    <p class="empty-state" hidden>По вашему запросу блюд нет. Попробуйте изменить поиск.</p>
    <p class="menu-note">Все блюда, цены и гости — часть вымышленной ресторанной концепции для лабораторной работы.</p>
  </section>
</main>
<script>window.DISHES=<?=json_encode($all,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;</script>
<?php footer_html(); ?>
