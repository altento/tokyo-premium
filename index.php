<?php
require __DIR__.'/bootstrap.php';
page_start('Tokyo Premium — японская кухня как искусство момента','Авторская японская кухня, сезонные вкусы и ритуал гостеприимства в вымышленном Tokyo Premium. Учебная концепция.');
header_html('home');
$featured=[];$featuredImages=[];
foreach(array_merge(dishes(true),dishes()) as $dish){$image=trim((string)$dish['image_url']);if($image===''||isset($featuredImages[$image]))continue;$featuredImages[$image]=true;$featured[]=$dish;if(count($featured)===4)break;}
$heroScenes=[
  ['image'=>'assets/generated/home-hero-salmon-nigiri-v3.webp','alt'=>'Нигири с лососем подают японскими палочками в тёплом свете — учебная концепция','eyebrow'=>setting('hero_eyebrow'),'title'=>'Вкус, которому','accent'=>'нужно время','lead'=>setting('hero_text'),'primary'=>['label'=>'Открыть меню','href'=>'menu.php'],'secondary'=>['label'=>'Выбрать вечер','href'=>'pages.php?page=booking']],
  ['image'=>'assets/generated/tokyo-seasonal-omakase-v2.webp','alt'=>'Сезонная композиция из японских блюд при свете фонаря — учебная концепция','eyebrow'=>'OMAKASE · СЕЗОННЫЙ МАРШРУТ','title'=>'Позвольте вечеру','accent'=>'постепенно раскрыться','lead'=>'Несколько подач, единый ритм и внимание к сезону. Изучите учебный сценарий Omakase и выберите формат ужина.','primary'=>['label'=>'История Omakase','href'=>'pages.php?page=omakase'],'secondary'=>['label'=>'Собрать свой сет','href'=>'set-builder.php']],
  ['image'=>'assets/generated/chef-at-counter.webp','alt'=>'Шеф формует нигири у суши-стойки — вымышленный образ для учебной концепции','eyebrow'=>'手仕事 · РЕМЕСЛО И ВНИМАНИЕ','title'=>'Точность в каждом','accent'=>'прикосновении','lead'=>'За каждым блюдом — спокойный жест, уважение к продукту и искусство замечать детали. Познакомьтесь с философией Tokyo Premium.','primary'=>['label'=>'Наша философия','href'=>'about.php'],'secondary'=>['label'=>'Пространство ресторана','href'=>'pages.php?page=gallery']]
];
?>
<main class="home-premium">
  <section class="home-hero" aria-labelledby="home-title" data-home-hero aria-roledescription="carousel" aria-label="Истории Tokyo Premium">
    <img class="home-hero-photo home-hero-photo--active" id="home-hero-photo" src="assets/generated/home-hero-salmon-nigiri-v3.webp" width="1672" height="944" fetchpriority="high" alt="Нигири с лососем, которое подают японскими палочками при свете фонарей">
    <img class="home-hero-photo home-hero-photo--next" data-home-hero-next width="1672" height="944" alt="" aria-hidden="true">
    <div class="home-hero-shade" aria-hidden="true"></div>
    <span class="home-kanji" lang="ja" aria-hidden="true">東京</span>
    <div class="home-hero-copy">
      <p class="eyebrow" data-home-hero-eyebrow><?=e($heroScenes[0]['eyebrow'])?></p>
      <h1 id="home-title"><span data-home-hero-title><?=e($heroScenes[0]['title'])?></span><br><em data-home-hero-accent><?=e($heroScenes[0]['accent'])?></em></h1>
      <p class="home-hero-lead" id="home-hero-lead" data-home-hero-lead><?=e($heroScenes[0]['lead'])?></p>
      <div class="button-row">
        <a class="button" data-home-hero-primary href="menu.php">Открыть меню <span aria-hidden="true">↗</span></a>
        <a class="button secondary" data-home-hero-secondary href="pages.php?page=booking">Выбрать вечер</a>
      </div>
    </div>
    <p class="home-hero-note"><span>旬</span> сезонность · точность · omotenashi</p>
    <a class="hero-scroll" href="#menu-selection" aria-label="Прокрутить к меню"><span></span> Листайте к истории вкуса</a>
    <div class="home-hero-controls" data-home-hero-controls role="group" aria-label="Сменить сюжет" hidden>
      <button type="button" class="home-hero-arrow" data-home-hero-step="-1" aria-label="Предыдущий сюжет">←</button>
      <div class="home-hero-dots" role="group" aria-label="Сюжеты">
        <?php foreach($heroScenes as $i=>$scene): ?><button type="button" data-home-hero-index="<?=$i?>" aria-label="Показать сюжет: <?=e($scene['eyebrow'])?>" aria-pressed="<?=$i===0?'true':'false'?>"></button><?php endforeach ?>
      </div>
      <button type="button" class="home-hero-arrow" data-home-hero-step="1" aria-label="Следующий сюжет">→</button>
    </div>
    <p class="sr-only" data-home-hero-status role="status" aria-live="polite"></p>
    <script type="application/json" id="home-hero-scenes"><?=json_encode($heroScenes,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
  </section>

  <section class="home-values" aria-label="Принципы кухни">
    <div><span class="value-mark" aria-hidden="true">一</span><span><strong>Сезонность</strong><small>ритм природы в меню</small></span></div>
    <div><span class="value-mark" aria-hidden="true">和</span><span><strong>Гармония</strong><small>баланс вкуса и формы</small></span></div>
    <div><span class="value-mark" aria-hidden="true">匠</span><span><strong>Мастерство</strong><small>точность каждого жеста</small></span></div>
    <div><span class="value-mark" aria-hidden="true">心</span><span><strong>Забота</strong><small>японское omotenashi</small></span></div>
  </section>

  <section class="home-menu-section" id="menu-selection">
    <div class="home-section-head">
      <div><p class="kicker">Выбор шефа · сезонная коллекция</p><h2>Вкус начинается<br><em>с одного мгновения</em></h2></div>
      <a class="text-link" href="menu.php">Все меню <span aria-hidden="true">↗</span></a>
    </div>
    <div class="home-dish-grid">
      <?php foreach($featured as $i=>$d): ?>
      <article class="home-dish-card reveal">
        <a class="home-dish-photo" href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>" aria-label="Подробнее: <?=e($d['name'])?>"><img src="<?=e($d['image_url'])?>" width="960" height="720" alt="<?=e($d['name'])?> — блюдо учебного меню" loading="lazy" decoding="async"></a>
        <button class="favorite-btn" type="button" data-favorite="<?=(int)$d['id']?>" aria-label="Сохранить <?=e($d['name'])?> в избранное">♡</button>
        <div class="home-dish-copy"><p class="dish-overline"><?=e($d['category_name'])?> <span>·</span> <?=e($d['weight'])?></p><h3><a href="pages.php?page=dish&amp;id=<?=(int)$d['id']?>"><?=e($d['name'])?></a></h3><p class="dish-description"><?=e($d['description'])?></p><div class="home-dish-bottom"><strong><?=number_format((int)$d['price'],0,'.',' ')?> ₸</strong><button class="add-to-cart" type="button" data-id="<?=(int)$d['id']?>" aria-label="Добавить <?=e($d['name'])?> в корзину"><span aria-hidden="true">+</span> Добавить</button></div></div>
      </article>
      <?php endforeach ?>
    </div>
    <p class="home-disclaimer">Учебное меню: названия, состав и цены придуманы для демонстрации проекта.</p>
  </section>

  <section class="home-philosophy">
    <div class="philosophy-photo-wrap"><img src="assets/generated/chef-at-counter.webp" width="1448" height="1096" alt="Вымышленный шеф Tokyo Premium бережно формует нигири у суши-стойки" loading="lazy" decoding="async"><span class="photo-index">手仕事 · ремесло</span></div>
    <div class="philosophy-copy"><p class="kicker">Философия Tokyo Premium</p><h2>Уважение к продукту.<br><em>Свобода для вкуса.</em></h2><p>Сезон задаёт направление, мастерство сохраняет чистоту вкуса, а пауза помогает заметить детали. Так устроена наша учебная история о японском столе.</p><p class="japanese-line" lang="ja">季節 · 技 · 心</p><a class="button secondary" href="about.php">Открыть философию <span aria-hidden="true">↗</span></a><span class="fictional-chef">Рэн Такахаси · вымышленный шеф-повар концепции</span></div>
    <div class="enso" aria-hidden="true"><span>和</span></div>
  </section>

  <section class="home-space">
    <div class="space-photo"><img src="assets/generated/restaurant-interior.webp" width="1672" height="944" alt="Вымышленный интерьер Tokyo Premium: ореховое дерево, тёплый свет и садовое окно" loading="lazy" decoding="async"></div>
    <div class="space-copy"><p class="kicker">Пространство · тёплая тишина</p><h2>Вечер начинается<br><em>ещё до первой подачи</em></h2><p>Тёмный орех, мягкий свет бумажных фонарей и камерная суши-стойка создают спокойный ритм для долгого ужина.</p><a class="text-link" href="pages.php?page=gallery">Исследовать пространство <span aria-hidden="true">↗</span></a></div>
    <span class="space-side-note" lang="ja" aria-hidden="true">間 · пространство</span>
  </section>

  <section class="home-omakase-strip">
    <div class="omakase-seal" aria-hidden="true"><span>おまかせ</span><b>匠</b></div>
    <div><p class="kicker">Omakase · доверьтесь выбору шефа</p><h2>История вкуса<br><em>в нескольких подачах</em></h2><p>Сезонный дегустационный маршрут — от лёгкого первого акцента до спокойного финала.</p><a class="button" href="pages.php?page=omakase">Узнать об Omakase <span aria-hidden="true">↗</span></a></div>
    <div class="omakase-decoration" aria-hidden="true"><span>一</span><span>期</span><span>一</span><span>会</span></div>
  </section>

  <section class="home-review-note">
    <p class="kicker">Истории гостей · демонстрационные примеры</p>
    <blockquote>«В нигири слышно главное: свежесть рыбы, тёплый рис и точность прикосновения»</blockquote>
    <p class="review-credit">Учебный отзыв · вымышленный персонаж</p>
    <a class="text-link" href="reviews.php">Читать и оставить отзыв <span aria-hidden="true">↗</span></a>
  </section>

  <section class="home-booking" id="booking">
    <div class="booking-intro"><p class="kicker">Оставьте место для вечера</p><h2>Ваш стол.<br><em>Ваш темп.</em></h2><p>В PHP-версии форма создаёт запрос на бронирование. На GitHub Pages данные останутся только в этом браузере.</p><span class="booking-jp" lang="ja" aria-hidden="true">今宵 · сегодня вечером</span></div>
    <form class="home-booking-form" data-api="booking"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><div class="field"><label for="guest-name">Имя</label><input required maxlength="80" id="guest-name" name="name" autocomplete="name"></div><div class="field"><label for="guest-phone">Телефон</label><input required maxlength="30" id="guest-phone" name="phone" type="tel" autocomplete="tel"></div><div class="field"><label for="booking-date">Дата</label><input required type="date" id="booking-date" name="date" min="<?=date('Y-m-d')?>"></div><div class="field"><label for="booking-time">Время</label><select id="booking-time" name="time" required><?php foreach(['12:00','13:00','14:00','17:00','18:00','19:00','20:00','21:00'] as $t): ?><option value="<?=e($t)?>"><?=e($t)?></option><?php endforeach ?></select></div><div class="field"><label for="guest-count">Гостей</label><select id="guest-count" name="guests"><?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$i===2?'selected':''?>><?=$i?> <?=$i===1?'гость':'гостя'?></option><?php endfor ?></select></div><div class="field"><label for="booking-zone-home">Зона</label><select id="booking-zone-home" name="zone"><option>Основной зал</option><option>Суши-стойка</option><option>Камерная комната</option></select></div><button class="button" type="submit">Отправить запрос <span aria-hidden="true">↗</span></button><p class="form-status" aria-live="polite"></p></form>
  </section>
</main>
<script>window.DISHES=<?=json_encode(dishes(),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;</script>
<script src="motion-system.js" defer></script>
<?php footer_html(); ?>
