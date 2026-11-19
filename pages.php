<?php
require __DIR__.'/bootstrap.php';
$view=(string)($_GET['page']??'omakase');
$all=dishes();
$savedCheckoutAddresses=$view==='cart'&&!empty($_SESSION['user_id'])?commerce_user_addresses(db(),(int)$_SESSION['user_id']):[];
$freedompayReady=$view==='cart'&&freedompay_is_enabled();
$freedompayTestMode=$freedompayReady?(bool)freedompay_config()['test_mode']:true;
$allById=[]; foreach($all as $dish)$allById[(int)$dish['id']]=$dish;
$detailDish=null;
if($view==='dish'){ $dishId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT); $detailDish=$dishId?($allById[(int)$dishId]??null):null; }
if($view==='dish'&&!$detailDish) http_response_code(404);
function ep_price(int $amount): string { return number_format($amount,0,'.',' '); }
function ep_hero(string $kanji,string $eyebrow,string $title,string $sub,string $copy,string $image,string $alt,string $primary,string $href,string $secondary='',string $extraClass='',string $secondaryHref='menu.php'): void {
    $panoramic = str_contains($image, 'omakase-chef-panorama-');
    $srcset = image_srcset($image);
    if ($panoramic) $srcset = 'assets/generated/omakase-chef-panorama-640.webp 640w, assets/generated/omakase-chef-panorama-1024.webp 1024w, assets/generated/omakase-chef-panorama-1920.webp 1920w';
    $cartHero = $image === 'assets/generated/cart-hero.webp';
    $width = $panoramic ? 1920 : ($cartHero ? 1672 : 1800);
    $height = $panoramic ? 640 : ($cartHero ? 941 : 1100);
?>
<section class="editorial-hero<?= $extraClass?' '.e($extraClass):'' ?>"><img class="editorial-hero-photo" src="<?=e($image)?>" srcset="<?=e($srcset)?>" sizes="100vw" width="<?=$width?>" height="<?=$height?>" fetchpriority="high" alt="<?=e($alt)?>"><div class="editorial-hero-shade"></div><div class="editorial-kanji" lang="ja"><?=e($kanji)?></div><div class="editorial-hero-copy"><div class="kicker"><?=e($eyebrow)?></div><h1><?=e($title)?></h1><p class="editorial-sub"><?=e($sub)?></p><p><?=e($copy)?></p><div class="button-row"><a class="button" href="<?=e($href)?>"><?=e($primary)?> ↗</a><?php if($secondary): ?><a class="button secondary" href="<?=e($secondaryHref)?>"><?=e($secondary)?> ↗</a><?php endif ?></div><span class="school-note">Учебная концепция · все бизнес-данные вымышлены</span></div></section>
<?php }
function ep_heading(string $eyebrow,string $title,string $copy=''): void { ?><div class="editorial-heading"><div class="kicker"><?=e($eyebrow)?></div><h2><?=e($title)?></h2><?php if($copy): ?><p><?=e($copy)?></p><?php endif ?></div><?php }
function ep_card(array $d,string $label=''): void { ?>
<article class="dish-card"><a class="editorial-product-image" href="pages.php?page=dish&amp;id=<?= (int)$d['id']?>"><img src="<?=e($d['image_url'])?>" srcset="<?=e(image_srcset($d['image_url']))?>" sizes="(max-width:760px) 90vw, 30vw" width="1200" height="800" alt="<?=e($d['name'])?>" loading="lazy" decoding="async"></a><div class="dish-meta"><?php if($label): ?><small class="editorial-badge"><?=e($label)?></small><?php endif ?><small><?=e($d['category_name'])?> · <?=e($d['weight'])?></small><button class="favorite-btn" type="button" data-favorite="<?= (int)$d['id']?>" aria-label="Сохранить <?=e($d['name'])?> в избранное">♡</button><h3><a href="pages.php?page=dish&amp;id=<?= (int)$d['id']?>"><?=e($d['name'])?></a></h3><p><?=e($d['description'])?></p><div class="dish-bottom"><span class="dish-price"><?=ep_price((int)$d['price'])?> ₸</span><button class="add-to-cart" type="button" data-id="<?= (int)$d['id']?>">Добавить +</button></div></div></article>
<?php }
[$metaTitle,$metaDescription] = match($view) {
 'dish'=>[$detailDish?($detailDish['name'].' — Tokyo Premium'):'Блюдо — Tokyo Premium',$detailDish?($detailDish['description'].' Вымышленная позиция учебного меню Tokyo Premium.'):'Описание блюда учебного меню Tokyo Premium.'],
 'omakase'=>['Omakase — Tokyo Premium','Дегустационный вечер Omakase в учебной концепции Tokyo Premium.'],
 'gallery'=>['Пространство — Tokyo Premium','Галерея интерьера и атмосферы учебной концепции Tokyo Premium.'],
 'booking'=>['Бронирование — Tokyo Premium','Параметры тестовой заявки на визит в Tokyo Premium.'],
 'contacts'=>['Контакты — Tokyo Premium','Вымышленные контакты учебного проекта Tokyo Premium.'],
 'cart'=>['Корзина — Tokyo Premium','Демо-корзина с учебными блюдами Tokyo Premium.'],
 default=>['Tokyo Premium','Страница учебной концепции японского ресторана.']
};
$metaImage = match($view) {
 'dish' => (string)($detailDish['image_url'] ?? 'assets/generated/home-hero-salmon-nigiri-v3.webp'),
 'omakase' => 'assets/generated/omakase-chef-panorama-1920.webp',
 'gallery' => 'assets/generated/room-main-hall.webp',
 'booking' => 'assets/generated/restaurant-interior.webp',
 'contacts' => 'assets/generated/contact-exterior.webp',
 'cart' => 'assets/generated/cart-hero.webp',
 default => 'assets/generated/home-hero-salmon-nigiri-v3.webp'
};
if ($view === 'dish') {
    ob_start();
    page_start($metaTitle,$metaDescription,$metaImage);
    $documentStart = (string) ob_get_clean();
    $headEnd = stripos($documentStart, '</head>');
    if ($headEnd === false) throw new RuntimeException('Cannot attach dish detail stylesheet: document head is missing.');
    $dishStylesheet = '<link rel="stylesheet" href="assets/dish-detail-refinement.css">';
    echo substr($documentStart, 0, $headEnd) . $dishStylesheet . substr($documentStart, $headEnd);
} else {
    page_start($metaTitle,$metaDescription,$metaImage);
}
header_html($view);
?>
<main class="editorial-page">
<?php if($view==='dish'):
  $dish=$detailDish;
  if(!$dish){ ?>
<section class="page-hero"><div class="kicker">Tokyo Premium</div><h1>Блюдо не найдено</h1><p>Вернитесь в меню, чтобы выбрать другую позицию.</p><a class="button" href="menu.php">Открыть меню ↗</a></section>
<?php } else {
  $pairingQuery=db()->prepare("SELECT dp.pairing_type,dp.paired_dish_id FROM dish_pairings dp JOIN dishes paired ON paired.id=dp.paired_dish_id AND paired.active=1 WHERE dp.dish_id=? ORDER BY CASE dp.pairing_type WHEN 'drink' THEN 0 WHEN 'sauce' THEN 1 WHEN 'dish' THEN 2 WHEN 'dessert' THEN 3 ELSE 4 END,dp.sort_order,paired.sort_order,paired.id LIMIT 4");
  $pairingQuery->execute([(int)$dish['id']]);
  $pairingRows=$pairingQuery->fetchAll();
  $pairingTypeLabels=['drink'=>'Напиток к блюду','sauce'=>'Соус к блюду','dish'=>'В пару к блюду','dessert'=>'Десерт к блюду'];
  $recommend=[]; $recommendLabels=[]; $recommendIds=[];
  foreach($pairingRows as $pairing){$pairedId=(int)$pairing['paired_dish_id'];if(!isset($allById[$pairedId])||$pairedId===(int)$dish['id'])continue;$recommend[]=$allById[$pairedId];$recommendIds[]=$pairedId;$recommendLabels[$pairedId]=$pairingTypeLabels[(string)$pairing['pairing_type']]??'Подходит к блюду';}
  $categoryFallback=array_values(array_filter($all,fn($d)=>(int)$d['id']!==(int)$dish['id']&&!in_array((int)$d['id'],$recommendIds,true)&&$d['category']===$dish['category']));
  foreach($categoryFallback as $candidate){$recommend[]=$candidate;if(count($recommend)>=4)break;}
  if(count($recommend)<4){foreach($all as $candidate){$candidateId=(int)$candidate['id'];if($candidateId===(int)$dish['id']||in_array($candidateId,$recommendIds,true)||array_filter($recommend,fn($d)=>(int)$d['id']===$candidateId))continue;$recommend[]=$candidate;if(count($recommend)>=4)break;}}
  $hasPairings=count($recommendIds)>0;
  $recommendHeading=$hasPairings?'Подходящие сочетания':'Другие блюда раздела';
  $compositionQuery=db()->prepare("SELECT i.name,di.is_optional FROM dish_ingredients di JOIN ingredients i ON i.slug=di.ingredient_slug AND i.active=1 WHERE di.dish_id=? ORDER BY di.sort_order,i.name");
  $compositionQuery->execute([(int)$dish['id']]);
  $dishComposition=$compositionQuery->fetchAll();
  $detailGalleryImages = [
    ['src'=>(string)$dish['image_url'], 'srcset'=>image_srcset((string)$dish['image_url']), 'alt'=>$dish['name'].' — основной ракурс'],
  ];
  if ((int)$dish['id'] === 3) {
    $detailGalleryImages[] = ['src'=>'assets/generated/nigiri-salmon-angle-02.jpg', 'srcset'=>'', 'alt'=>$dish['name'].' — второй ракурс'];
    $detailGalleryImages[] = ['src'=>'assets/generated/nigiri-salmon-angle-03.jpg', 'srcset'=>'', 'alt'=>$dish['name'].' — крупный план подачи'];
  }

?>
<nav class="breadcrumbs" aria-label="Навигация"><a href="index.php">Главная</a><span>›</span><a href="menu.php">Меню</a><span>›</span><span aria-current="page"><?=e($dish['name'])?></span></nav>
<section class="section detail-feature detail-feature--product">
  <div class="detail-product-panel">
  <div class="detail-product-heading"><div class="kicker"><?=e($dish['category_name'])?> · <?=e($dish['weight'])?> · ВЫМЫШЛЕННОЕ МЕНЮ</div><h1 class="detail-product-title"><?=e($dish['name'])?></h1><p class="detail-product-intro">Японская подача, собранная вокруг чистого вкуса продукта.</p></div>
  <div class="detail-copy">
    <p><?=e($dish['description'])?></p>
    <div class="detail-product-price"><div><span class="kicker">Цена учебного меню</span><strong data-dish-price data-price-value="<?= (int)$dish['price']?>"><?=ep_price((int)$dish['price'])?> ₸</strong></div><small>за порцию · учебная концепция</small></div>
    <div class="detail-actions"><div class="detail-purchase-group"><div class="quantity-control" aria-label="Количество порций"><button type="button" data-qty-step="-1" aria-label="Уменьшить количество">−</button><output id="dish-qty">1</output><button type="button" data-qty-step="1" aria-label="Увеличить количество">+</button></div><button class="button add-to-cart" type="button" data-qty="1" data-id="<?= (int)$dish['id']?>">Добавить в заказ</button></div></div>
    <p class="menu-note detail-accuracy-note">Состав показан только там, где он внесён в учебную карту. Аллергенные сведения не установлены.</p>
  </div>
  </div>
  <?php $hasDetailGallery=count($detailGalleryImages)>1; ?>
  <div class="detail-gallery<?=$hasDetailGallery?' detail-gallery--has-thumbnails':''?>" data-detail-gallery>
    <?php if($hasDetailGallery): ?><div class="detail-thumbnails" role="group" aria-label="Выберите ракурс блюда"><?php foreach($detailGalleryImages as $index=>$galleryImage): ?><button class="detail-thumbnail<?=$index===0?' is-active':''?>" type="button" data-detail-gallery-image="<?=e($galleryImage['src'])?>" data-detail-srcset="<?=e($galleryImage['srcset'])?>" data-detail-alt="<?=e($galleryImage['alt'])?>" data-detail-caption="<?=e('Фото '.($index+1).' из '.count($detailGalleryImages).' · '.($index===0?'Основной ракурс':'Дополнительный ракурс'))?>" aria-label="Показать фото <?=($index+1)?> из <?=count($detailGalleryImages)?>" aria-controls="dish-main-image" aria-pressed="<?=$index===0?'true':'false'?>"><img src="<?=e($galleryImage['src'])?>" width="1200" height="900" alt="" loading="<?=$index===0?'eager':'lazy'?>" decoding="async"></button><?php endforeach ?></div><?php endif ?>
    <div class="detail-visual"><img id="dish-main-image" src="<?=e($detailGalleryImages[0]['src'])?>" srcset="<?=e($detailGalleryImages[0]['srcset'])?>" sizes="(max-width:760px) 100vw, 58vw" width="1200" height="900" alt="<?=e($detailGalleryImages[0]['alt'])?>" fetchpriority="high" decoding="async"><button class="favorite-btn detail-favorite-btn" type="button" data-favorite="<?= (int)$dish['id']?>" aria-label="Сохранить <?=e($dish['name'])?> в избранное" aria-pressed="false">♡</button><span class="image-caption" data-detail-gallery-caption aria-live="polite"><?=$hasDetailGallery?'Фото 1 из '.count($detailGalleryImages).' · основной ракурс':'Одна фотография блюда · дополнительные ракурсы не представлены'?></span></div>
  </div>
</section>
<section class="detail-fact-band" data-dish-facts aria-label="Сведения об учебной позиции"><article data-dish-fact="category"><span>Раздел</span><strong><?=e($dish['category_name'])?></strong></article><article data-dish-fact="portion"><span>Порция</span><strong><?=e($dish['weight'])?></strong></article><article data-dish-fact="ingredients" data-count="<?=count($dishComposition)?>"><span>Состав из учебной карты</span><strong><?=count($dishComposition)>0?'Внесено: '.count($dishComposition):'Не указан'?></strong></article><article data-dish-fact="photos" data-count="<?=count($detailGalleryImages)?>"><span>Фотографии</span><strong><?=count($detailGalleryImages)?> фото</strong></article></section>
<section class="section dish-chef-section"><div class="dish-chef-story"><img src="assets/generated/chef-at-counter.webp" width="1480" height="1000" alt="Иллюстративный кадр учебной концепции: шеф-повар готовит нигири" loading="lazy" decoding="async"><div class="editorial-note"><div class="kicker">Подача от шефа концепции</div><blockquote>Точная подача помогает раскрыть продукт — спокойно, без лишних акцентов.</blockquote><p class="menu-note">Редакционный текст и фотография относятся к учебной концепции и не описывают реального сотрудника.</p></div></div></section>
<section class="section dish-composition-section" aria-labelledby="dish-composition-title"><div class="editorial-heading"><div class="kicker">Состав из учебной карты</div><h2 id="dish-composition-title">Ингредиенты блюда</h2><p>Список собран из описания позиции каталога; он не заменяет проверку аллергенов рестораном.</p></div><div class="detail-ingredients" data-dish-composition><?php if($dishComposition): foreach($dishComposition as $index=>$component): ?><article class="detail-ingredient-card"><span><?=sprintf('%02d',$index+1)?></span><h3><?=e($component['name'])?></h3><p><?=!empty($component['is_optional'])?'По желанию':'Указан в учебной карте'?></p></article><?php endforeach; else: ?><article class="detail-ingredient-card detail-ingredient-card--note"><span>—</span><h3>Авторский состав</h3><p><?=e($dish['description'])?></p><small>Подробный список компонентов для этой позиции не внесён.</small></article><?php endif ?></div></section>
<section class="section dark dish-related-section"><div class="section-head"><div><div class="kicker"><?=$hasPairings?'Рекомендуем дополнить':'Продолжить знакомство · '.e($dish['category_name'])?></div><h2><?=e($recommendHeading)?></h2></div><a class="button secondary" href="menu.php">Все меню ↗</a></div><div class="dish-grid dish-grid-related"><?php foreach($recommend as $d) ep_card($d,$recommendLabels[(int)$d['id']]??''); ?></div></section>
<?php } elseif($view==='omakase'): ?>
<?php ep_hero('お任せ','ВЫБОР ШЕФА · РИТУАЛ ВКУСА','Omakase','Ужин от шефа','Доверить выбор шефу — значит позволить сезону вести вас от первой лёгкой подачи к глубокому финалу.','assets/generated/omakase-chef-panorama-1920.webp','Шеф готовит нигири для дегустационного меню в камерном японском ресторане','Выбрать формат','#omakase-tiers','Открыть меню','omakase-hero'); ?>
<?php
$omakasePlan=[
 ['Знакомство',12],
 ['Нигири · тунец',6],
 ['Нигири · лосось',3],
 ['Горячая подача',15],
 ['Десерт',19],
];
$omakaseCourses=[];
foreach($omakasePlan as [$stageLabel,$dishId]) if(isset($allById[$dishId])) $omakaseCourses[]=['stage'=>$stageLabel,'dish'=>$allById[$dishId]];
if(!$omakaseCourses) foreach(array_slice($all,0,5) as $dish) $omakaseCourses[]=['stage'=>$dish['category_name'],'dish'=>$dish];
?>
<section class="section dark omakase-philosophy-strip" aria-labelledby="omakase-philosophy-title"><div class="omakase-philosophy-copy"><div class="kicker">Философия Omakase</div><h2 id="omakase-philosophy-title">Доверие шефу</h2><p>Сезон задаёт настроение, мастерство раскрывает продукт, а последовательность подач складывается в цельный вечер. Здесь Omakase — учебная концепция, а не подтверждённая ресторанная услуга.</p></div><div class="omakase-principles" aria-label="Принципы дегустационной концепции"><article><span lang="ja" aria-hidden="true">季</span><h3>Сезонность</h3><p>Вкусы в естественном ритме года</p></article><article><span lang="ja" aria-hidden="true">匠</span><h3>Мастерство</h3><p>Точность и уважение к продукту</p></article><article><span lang="ja" aria-hidden="true">和</span><h3>Гармония</h3><p>Баланс каждой подачи</p></article></div></section>
<section class="section tp20-omakase-experience" data-tp-omakase aria-label="Интерактивная история Omakase"><div class="tp20-course-heading"><div><div class="kicker">お任せ · «полагаюсь на вас»</div><h2>История одного вечера</h2><p>Пять подач, пять оттенков вкуса: от свежести тунца к хрустящей темпуре и мягкому финалу матча. Пример из учебного каталога; состав и аллергены уточняются отдельно.</p></div><div class="tp20-course-progress" data-tp-omakase-progress aria-label="Прогресс знакомства с меню"><span></span></div></div><div class="tp20-course-tabs" role="tablist" aria-label="Позиции учебной последовательности"><?php foreach($omakaseCourses as $i=>$course): $dish=$course['dish']; ?><button type="button" id="tp-omakase-tab-<?=$i+1?>" data-tp-course data-step="<?=$i+1?>" aria-controls="tp-omakase-panel-<?=$i+1?>" aria-selected="<?=$i===0?'true':'false'?>" aria-label="Позиция <?=sprintf('%02d',$i+1)?>: <?=e($course['stage'])?> — <?=e($dish['name'])?>"><img src="<?=e($dish['image_url'])?>" alt="" aria-hidden="true" loading="lazy" decoding="async"><span class="tp20-course-tab-copy"><span class="tp20-course-tab-stage"><span><?=sprintf('%02d',$i+1)?></span> <?=e($course['stage'])?></span><strong><?=e($dish['name'])?></strong></span></button><?php endforeach ?></div><div class="tp20-course-navigation" aria-label="Переход между подачами"><button type="button" data-tp-course-prev aria-label="Предыдущая подача" disabled>← Назад</button><p data-tp-course-current role="status" aria-live="polite" aria-atomic="true">Подача 1 из <?=count($omakaseCourses)?></p><button type="button" data-tp-course-next aria-label="Следующая подача">Далее →</button></div><div class="tp20-course-panels"><?php foreach($omakaseCourses as $i=>$course): $dish=$course['dish']; ?><article class="tp20-course-panel" id="tp-omakase-panel-<?=$i+1?>" data-tp-course-panel data-step="<?=$i+1?>" <?=$i===0?'':'hidden'?>><div class="tp20-course-photo"><img src="<?=e($dish['image_url'])?>" srcset="<?=e(image_srcset($dish['image_url']))?>" alt="<?=e($dish['name'])?> — демонстрационная фотография блюда" loading="<?=$i===0?'eager':'lazy'?>" decoding="async"></div><div class="tp20-course-story"><span class="kicker">ПОЗИЦИЯ <?=sprintf('%02d',$i+1)?> / <?=sprintf('%02d',count($omakaseCourses))?> · <?=e($dish['category_name'])?></span><h3><?=e($dish['name'])?></h3><p><?=e($dish['description'])?></p><dl><div><dt>Порция</dt><dd><?=e($dish['weight'])?></dd></div><div><dt>Цена учебного меню</dt><dd><?=ep_price((int)$dish['price'])?> ₸</dd></div></dl><p class="tp20-course-note">Учебный пример; состав и аллергены проверяйте перед заказом.</p><a class="button secondary" href="pages.php?page=dish&amp;id=<?=(int)$dish['id']?>">Подробнее о блюде ↗</a></div></article><?php endforeach ?></div></section>
<section class="section dark" id="omakase-tiers" aria-labelledby="omakase-tiers-title"><div class="editorial-heading"><div class="kicker">Три варианта · цены учебного меню</div><h2 id="omakase-tiers-title">Выберите темп своего вечера</h2><p>Все цены вымышлены и не являются предложением действующего ресторана.</p></div><div class="experience-tiers"><?php foreach([['Начало','6 подач · около 90 минут',12900],['Сезонный','9 подач · около 120 минут',16900],['Премиальный','12 подач · около 150 минут',24900]] as $tier): ?><article class="tier-card<?= $tier[0]==='Сезонный'?' tier-card-featured':'' ?>"><span class="kicker"><?=e($tier[0]==='Сезонный'?'ВЫБОР ШЕФА · УЧЕБНЫЙ':$tier[0])?></span><h3>Omakase <?=e($tier[0])?></h3><p><?=e($tier[1])?></p><strong><?=ep_price($tier[2])?> ₸</strong><a class="button secondary" data-tp-omakase-select data-format="<?=e($tier[0])?>" aria-label="Выбрать Omakase <?=e($tier[0])?>" href="pages.php?page=booking&amp;format=<?=rawurlencode($tier[0])?>">Выбрать формат ↗</a></article><?php endforeach ?></div></section>
<section class="omakase-reservation-band" aria-labelledby="omakase-reservation-title"><img src="assets/generated/room-main-hall.webp" alt="Вечерняя сервировка в основном зале учебной концепции" loading="lazy" decoding="async"><div class="omakase-reservation-copy"><div class="kicker">Выбор для первого знакомства · учебный пример</div><h2 id="omakase-reservation-title">Пусть вечер задаст свой ритм</h2><p>Сезонный формат показан как рекомендация концепции. Перед отправкой заявки его можно изменить; отправка не подтверждает бронь.</p><a class="button" href="pages.php?page=booking&amp;format=Сезонный">Выбрать сезонный формат ↗</a></div></section>
<section class="section image-story"><img src="assets/generated/chef-at-counter.webp" srcset="<?=e(image_srcset('assets/generated/chef-at-counter.webp'))?>" alt="Шеф завершает подачу дегустационного меню, иллюстрация учебной концепции" loading="lazy" decoding="async"><div><div class="kicker">За кулисами Omakase · фото</div><h2>Точность начинается до подачи</h2><p>Точный срез, температура риса, последний штрих перед подачей. Работа за стойкой задаёт ритм всему вечеру — без спешки, с вниманием к каждой детали.</p><a class="button" href="pages.php?page=booking&amp;format=Сезонный">Бронирование Omakase ↗</a></div></section>
<section class="omakase-quote" aria-label="Философия Omakase"><span lang="ja">一期一会</span><p>Каждая встреча складывается из сезона, внимания и момента.</p><small>Текст учебной концепции · не цитата реального шефа</small></section>
<?php elseif($view==='gallery'): ?>
<?php ep_hero('空間','ПРОСТРАНСТВО · TOKYO PREMIUM','Пространство','Атмосфера современной Японии','Природные материалы, мягкий свет и детали создают место для неспешного разговора. Все фотографии и сведения о зонах относятся к учебной концепции.','assets/generated/room-main-hall.webp','Главный зал японского ресторана, учебное изображение','Смотреть галерею','#gallery','','gallery-hero'); ?>
<section class="space-values-strip" aria-label="Визуальные ориентиры учебного пространства">
  <article><span aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M4 12h24M8 12V7h16v5M7 15h18M10 15v10m12-10v10M5 27h22M12 7V4m8 3V4"/></svg></span><div><h3>Японская эстетика</h3><p>Визуальное направление учебной концепции</p></div></article>
  <article><span aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M16 27V14m0 5c-6 1-10-3-10-9 6 0 10 3 10 9Zm0-4c0-6 4-10 10-10 0 6-4 10-10 10ZM7 27h18"/></svg></span><div><h3>Природные фактуры</h3><p>Дерево и камень в иллюстративных сценах</p></div></article>
  <article><span aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M4 12h24M6 12l3-6h14l3 6M8 15v9m16-9v9M5 27h22M12 18h8"/></svg></span><div><h3>Зоны встречи</h3><p>Четыре условных формата для сценариев страницы</p></div></article>
  <article><span aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M10 5h12l2 11H8l2-11Zm-2 11h16l-2 4H10l-2-4Zm8 4v6m-6 2h12M13 8h6"/></svg></span><div><h3>Мягкий свет</h3><p>Атмосферный принцип дизайна, не обещание объекта</p></div></article>
</section>
<section class="section" id="spaces"><div class="editorial-heading"><div class="kicker">Зоны концепции</div><h2>Разные форматы встречи</h2><p>Без фиктивного 360°-тура: ниже — галерея иллюстративных фотографий и описания зон.</p></div><div class="zone-grid"><?php foreach([['Суши-бар','До 8 гостей','assets/generated/room-sushi-counter.webp','Место, где можно наблюдать за работой поваров.'],['Основной зал','До 40 гостей','assets/generated/room-main-hall.webp','Просторное пространство для ужинов, встреч и особых событий.'],['Приватная комната','До 10 гостей','assets/generated/room-private-dining.webp','Уединённое место для важных встреч и разговоров.'],['Летняя терраса','До 20 гостей','assets/generated/room-garden-terrace.webp','Атмосферная терраса с видом на японский сад.']] as $i=>$zone): ?><article class="zone-card" data-gallery-filter="<?=['interior','interior','details','atmosphere'][$i]?>"><button type="button" class="lightbox-trigger" data-lightbox="<?=e($zone[2])?>" aria-label="Увеличить фото: <?=e($zone[0])?>"><img src="<?=e($zone[2])?>" alt="<?=e($zone[0])?> — иллюстративное изображение учебной концепции" loading="lazy" decoding="async"><span>↗</span></button><div><span class="kicker"><?=sprintf('%02d',$i+1)?> · учебная зона</span><h3><?=e($zone[0])?></h3><p><?=e($zone[3])?></p><small><?=e($zone[1])?></small><a class="zone-booking-link" href="pages.php?page=booking&amp;zone=<?=rawurlencode($zone[0])?>">Предпочесть эту зону в заявке <span aria-hidden="true">↗</span></a></div></article><?php endforeach ?></div></section>
<section class="section dark" id="gallery" data-tp-gallery><div class="gallery-toolbar"><div class="editorial-heading"><div class="kicker">Кадры пространства</div><h2>Свет, фактура, тишина</h2></div><div class="filter-row" role="group" aria-label="Фильтр галереи"><button class="filter-btn active" data-gallery="all">Все</button><button class="filter-btn" data-gallery="interior">Интерьер</button><button class="filter-btn" data-gallery="details">Детали</button><button class="filter-btn" data-gallery="atmosphere">Атмосфера</button></div></div><div class="gallery-grid" id="gallery-grid"><?php foreach([['assets/generated/restaurant-interior.webp','Интерьер у садового окна','interior'],['assets/generated/omakase-chef-panorama-1920.webp','Бар шефа','interior'],['assets/generated/nigiri-salmon-angle-02.jpg','Нигири крупным планом','details'],['assets/generated/contact-landscape-torii-v1.webp','Вечерний сад','atmosphere'],['assets/generated/chef-at-counter.webp','Работа шефа','details'],['assets/food/17-omakase-set.webp','Дегустационная подача','details'],['assets/generated/contact-exterior.webp','Вход и сад','atmosphere'],['assets/food/04-matcha-latte.webp','Чайная пауза','details']] as $i=>$item): ?><button class="gallery-tile" type="button" data-gallery-filter="<?=e($item[2])?>" data-lightbox="<?=e($item[0])?>" aria-label="Увеличить: <?=e($item[1])?>"><img src="<?=e($item[0])?>" alt="<?=e($item[1])?> — учебное изображение" loading="lazy" decoding="async"><span><?=e($item[1])?></span></button><?php endforeach ?></div><dialog class="image-lightbox" aria-label="Просмотр фотографии"><button class="lightbox-close" type="button" aria-label="Закрыть просмотр">×</button><img alt=""><p class="lightbox-caption" data-tp-gallery-caption data-lightbox-caption aria-live="polite" aria-atomic="true"></p></dialog></section>
<?php elseif($view==='booking'): ?>
<?php ep_hero('予約','ПЛАНИРУЙТЕ ВЕЧЕР','Бронирование','Забронируйте свой столик.','В PHP-версии форма отправит заявку для последующего подтверждения. На GitHub Pages она сохранится локально и не резервирует стол.','assets/generated/restaurant-interior.webp','Зал с сакурой и сервировкой в вымышленной учебной концепции','Выбрать параметры','#booking-form','','booking-hero'); ?>
<section class="section booking-benefits-section"><div class="booking-benefits"><?php foreach([['01','Спокойный выбор','Заранее подберите подходящую зону.'],['02','Пожелания','Укажите повод или предпочтения.'],['03','Подтверждение','Заявка — запрос, бронь подтверждается отдельно.']] as $b): ?><article><span><?=e($b[0])?></span><h3><?=e($b[1])?></h3><p><?=e($b[2])?></p></article><?php endforeach ?></div></section>
<section class="section dark" id="booking-form"><div class="editorial-heading"><div class="kicker">Демонстрационная заявка</div><h2>Соберите параметры визита</h2><p>Схема выбора помогает указать предпочтительный столик, но не показывает реальную доступность.</p></div><div class="booking-layout"><form class="booking-form" id="full-booking-form" data-api="booking"><p class="school-note">Учебная форма: используйте вымышленные контактные данные. PHP отправляет email только при настроенном SMTP; GitHub Pages сохраняет запись только в этом браузере и письмо не отправляет. Заявка не резервирует стол.</p><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="table" value=""><div class="field"><label for="full-guest-name">Имя</label><input id="full-guest-name" name="name" required maxlength="80" autocomplete="name"></div><div class="field"><label for="full-guest-phone">Телефон</label><input id="full-guest-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel"></div><div class="field"><label for="full-guest-email">Email для ответа</label><input id="full-guest-email" name="email" type="email" required maxlength="190" autocomplete="email"></div><div class="field"><label for="full-guest-date">Дата</label><input id="full-guest-date" name="date" type="date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+365 days'))?>" required></div><div class="field"><label for="full-guest-time">Время</label><select id="full-guest-time" name="time" required><?php foreach(['12:00','13:00','14:00','17:00','18:00','19:00','20:00','21:00'] as $t): ?><option value="<?=e($t)?>"><?=e($t)?></option><?php endforeach ?></select></div><div class="field"><label for="full-guest-count">Гостей</label><select id="full-guest-count" name="guests"><?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>" <?=$i===2?'selected':''?>><?=$i?></option><?php endfor ?></select></div><div class="field"><label for="booking-zone">Зона</label><select id="booking-zone" name="zone"><?php foreach(['Основной зал','Суши-бар','Приватная комната','Летняя терраса'] as $zone): ?><option value="<?=e($zone)?>" <?=($_GET['zone']??'')===$zone?'selected':''?>><?=e($zone)?></option><?php endforeach ?></select></div><div class="field full"><label for="booking-format">Формат вечера</label><select id="booking-format" name="format"><option value="" <?=empty($_GET['format'])?'selected':''?>>Обычный ужин</option><?php foreach(['Начало','Сезонный','Премиальный'] as $format): ?><option value="<?=e($format)?>" <?=($_GET['format']??'')===$format?'selected':''?>>Omakase · <?=e($format)?></option><?php endforeach ?></select></div><div class="field full"><label for="booking-note">Пожелания</label><textarea id="booking-note" name="message" maxlength="240" placeholder="Повод, предпочтения по залу"></textarea></div><p class="table-selection" aria-live="polite">Столик не выбран. Выберите номер на схеме или оставьте поле пустым.</p><p class="form-status" aria-live="polite"></p></form><div class="table-map-wrap"><div class="kicker">Схема учебного зала · выбор пожелания</div><div class="tp20-floorplan-layout"><div data-tp-floorplan data-tp-floorplan-src="assets/seat-plan.svg" data-tp-form="#full-booking-form"><p class="form-status" role="status">Выберите зону и предпочтительный стол. Схема не подтверждает наличие.</p></div><img data-tp-zone-photo src="assets/generated/room-main-hall.webp" alt="Основной зал — иллюстративное изображение учебной концепции" loading="lazy" decoding="async"></div><p class="table-selection" data-tp-floorplan-selection role="status" aria-live="polite">Зона не выбрана. Выбор стола — только пожелание.</p><p class="menu-note" data-tp-seat-caption>Доступность и вместимость уточняются отдельно; бронирование подтверждается после обработки заявки.</p><button class="button booking-submit" type="submit" form="full-booking-form">Отправить запрос ↗</button></div></div></section>
<section class="section faq-section booking-faq-section" aria-labelledby="booking-faq-title">
  <div class="booking-faq-art">
    <figure>
      <img src="assets/generated/booking-faq-landscape.svg" alt="Оригинальная иллюстрация учебной концепции: пагода у воды под горой Фудзи и ветвью сакуры" loading="lazy" decoding="async">
      <figcaption><span lang="ja">一期一会</span><span>Пусть у вечера будет свой ритм</span></figcaption>
    </figure>
    <p>Tokyo Premium — вымышленная учебная концепция. Подтверждённые сведения об услугах и месте отсутствуют.</p>
  </div>
  <div class="booking-faq-content">
    <div class="editorial-heading"><div class="kicker">Перед визитом · よくある質問</div><h2 id="booking-faq-title">Частые вопросы</h2><p>Коротко о том, что происходит после отправки учебной заявки.</p></div>
    <div class="booking-faq-list">
      <details open><summary>Когда заявка становится подтверждённой?</summary><p>В PHP-версии создаётся запрос, который ещё должен подтвердить сотрудник ресторана. На GitHub Pages запись хранится только в этом браузере и никому не отправляется.</p></details>
      <details><summary>Придёт ли подтверждение по email?</summary><p>PHP может отправить письмо, если администратор настроил SMTP на сервере. Без этих настроек заявка остаётся в очереди; статическая версия Pages письма не отправляет.</p></details>
      <details><summary>Можно ли указать повод или предпочтения?</summary><p>Да, используйте поле пожеланий. В демонстрационной версии не отправляйте реальные контактные, медицинские или иные чувствительные данные.</p></details>
      <details><summary>Можно ли выбрать Omakase?</summary><p>Выберите один из учебных форматов в поле «Формат вечера». Это пожелание к заявке, а не обещание доступности дегустационного ужина.</p></details>
    </div>
  </div>
</section>
<?php elseif($view==='contacts'): ?>
<?php
$contactTel = preg_replace('/[^0-9+]/', '', (string)setting('phone', '+7 (7172) 55-18-26'));
if (!is_string($contactTel) || !preg_match('/^\+?[0-9]{7,15}$/', $contactTel)) $contactTel = '+77172551826';
ep_hero('連絡先','TOKYO PREMIUM · УЧЕБНАЯ КОНЦЕПЦИЯ','Контакты','Всегда на связи','Свяжитесь с командой учебного проекта по вопросам меню, бронирования и формата вечера. Все контакты и сведения о месте вымышлены.','assets/generated/contact-exterior.webp','Вымышленный вход японского ресторана, оригинальное учебное изображение','Позвонить сейчас','tel:'.$contactTel,'Написать нам','contact-hero','#contact-form');
?>
<section class="section contact-overview" aria-label="Контакты и расположение">
  <div class="contact-summary">
    <div class="contact-section-heading"><span aria-hidden="true"></span><h2>Наши контакты</h2></div>
    <ul class="contact-facts">
      <li><span class="contact-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h3l2 5-2.2 1.7a15 15 0 0 0 4.5 4.5L16 12l5 2v3c0 1.1-.9 2-2 2C10.7 19 5 13.3 5 6c0-1.7.7-3 2-3Z"/></svg></span><span class="contact-fact-copy"><span>Телефон · демо</span><a href="tel:+77172551826"><?=e(setting('phone','+7 (7172) 55-18-26'))?></a><small>Вымышленный номер учебного проекта</small></span></li>
      <li><span class="contact-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m4 7 8 6 8-6"/></svg></span><span class="contact-fact-copy"><span>Email · демо</span><a href="mailto:hello@tokyopremium.example">hello@tokyopremium.example</a><small>Адрес на специальном домене .example</small></span></li>
      <li><span class="contact-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><span class="contact-fact-copy"><span>Адрес · вымышленный</span><p><?=e(setting('address','Астана, квартал Ханами'))?></p><small>Не используйте для навигации</small></span></li>
    </ul>
  </div>
  <div class="contact-map-panel">
    <div class="contact-section-heading"><span aria-hidden="true"></span><h2>Как нас найти</h2></div>
    <div class="fictional-map" role="img" aria-label="Декоративная карта вымышленного района; не является реальной картой и не строит маршрут">
      <svg class="contact-map-art" viewBox="0 0 900 440" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs><pattern id="contact-map-blocks" width="150" height="112" patternUnits="userSpaceOnUse"><rect x="8" y="9" width="58" height="41" rx="5" fill="#27231d" stroke="#554638" stroke-width="1.5"/><rect x="76" y="10" width="64" height="31" rx="5" fill="#302920" stroke="#594b38" stroke-width="1.5"/><rect x="21" y="62" width="46" height="39" rx="5" fill="#30271e" stroke="#5b4936" stroke-width="1.5"/><rect x="78" y="53" width="62" height="49" rx="5" fill="#25241d" stroke="#514b37" stroke-width="1.5"/></pattern><linearGradient id="contact-map-ambient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#171512"/><stop offset=".55" stop-color="#242019"/><stop offset="1" stop-color="#171b15"/></linearGradient></defs>
        <rect width="900" height="440" fill="url(#contact-map-ambient)"/><rect width="900" height="440" fill="url(#contact-map-blocks)" opacity=".9"/>
        <path d="M-30 358 270 223 970 58M62-35l240 475M-15 94l900 276" fill="none" stroke="#8a6946" stroke-opacity=".45" stroke-width="27"/><path d="M-30 358 270 223 970 58M62-35l240 475M-15 94l900 276" fill="none" stroke="#d2ad70" stroke-opacity=".18" stroke-width="2" stroke-dasharray="8 12"/>
        <path d="M605 292c34-39 65-52 112-50 48 2 69 25 96 69-44 29-78 43-124 35-37-7-62-24-84-54Z" fill="#263426" stroke="#64714a" stroke-opacity=".52" stroke-width="2"/><path d="M637 296c37-18 90-18 136 8M665 269c3 37 25 60 56 73" fill="none" stroke="#83936a" stroke-opacity=".3" stroke-width="3"/>
        <g fill="#ddba79" opacity=".58"><circle cx="192" cy="130" r="3"/><circle cx="450" cy="93" r="3"/><circle cx="760" cy="175" r="3"/><circle cx="390" cy="350" r="3"/><circle cx="140" cy="285" r="3"/></g>
      </svg>
      <span class="map-road road-one">САКУРА-ДОРИ</span><span class="map-road road-two">ПЕРЕУЛОК ХАНАМИ</span>
      <div class="map-destination"><span class="map-destination-mark" aria-hidden="true">鳥居</span><span><strong>Tokyo Premium</strong><small>Учебная локация · вымышленный адрес</small></span></div>
      <span class="map-transit"><span aria-hidden="true">◉</span> ХАНАМИ · условная станция</span>
      <span class="map-caption">Учебная схема · не маршрут</span>
    </div>
    <p class="contact-map-note">Схема создана для оформления страницы. Она не показывает реальную географию и не поддерживает навигацию.</p>
  </div>
</section>
<section class="section dark contact-response-section" id="contact-form" aria-label="Написать в Tokyo Premium">
  <div class="contact-response-grid">
    <div class="contact-message-panel">
      <div class="contact-section-heading"><span aria-hidden="true"></span><div><span class="kicker">Учебное обращение</span><h2>Напишите нам</h2></div></div>
      <p class="contact-form-intro">Сообщение останется в учебном проекте: в PHP — в локальной базе, на GitHub Pages — только в этом браузере. Реальному ресторану оно не отправляется.</p>
      <form class="contact-form" data-api="contact"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><div class="field"><label for="contact-name">Ваше имя</label><input id="contact-name" name="name" required maxlength="80" autocomplete="name"></div><div class="field"><label for="contact-email">Ваш e-mail</label><input id="contact-email" name="email" type="email" required maxlength="160" autocomplete="email"></div><div class="field full"><label for="contact-subject">Тема обращения</label><select id="contact-subject" name="subject"><option>Общий вопрос</option><option>Учебное бронирование</option><option>Учебное меню</option></select></div><div class="field full"><label for="contact-message">Ваше сообщение</label><textarea id="contact-message" name="message" minlength="5" maxlength="1000" required></textarea></div><button class="button" type="submit">Сохранить демо-сообщение ↗</button><p class="form-status" aria-live="polite"></p></form>
    </div>
    <aside class="contact-hours-panel">
      <div class="contact-section-heading"><span aria-hidden="true"></span><h2>Время работы</h2></div>
      <div class="contact-hours-row"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div><strong><?=e(setting('hours','Ежедневно · 12:00–23:00'))?></strong><small>Демонстрационный график · условия вымышлены</small></div></div>
      <div class="contact-reservation-card"><span class="kicker">Планируете визит?</span><h3>Выберите удобное время</h3><p>Форма отправит учебный запрос; столик не резервируется автоматически.</p><a class="button secondary" href="pages.php?page=booking">Перейти к бронированию ↗</a></div>
    </aside>
    <figure class="contact-interior"><img src="assets/generated/restaurant-interior.webp" alt="Интерьер японского ресторана с мягким светом — оригинальная иллюстрация учебной концепции" loading="lazy" decoding="async"><figcaption><span>Атмосфера Tokyo Premium</span><small>Вымышленный интерьер · учебное изображение</small></figcaption></figure>
  </div>
</section>
<section class="section contact-visit-section" aria-labelledby="contact-visit-title">
  <figure class="contact-visit-image"><img src="assets/generated/contact-landscape-torii-v1.webp" alt="Тории у воды и гора Фудзи на закате — декоративная иллюстрация учебной концепции" loading="lazy" decoding="async"><figcaption>Японский пейзаж · учебная иллюстрация, не место ресторана</figcaption></figure>
  <div class="contact-visit-copy"><div class="kicker">ご来店の前に · перед визитом</div><div class="contact-section-heading"><span aria-hidden="true"></span><h2 id="contact-visit-title">Спланируйте вечер заранее</h2></div><p>Tokyo Premium — учебная концепция с вымышленным адресом. Здесь нет реального маршрута, подтверждённых сведений о парковке или доступности помещения.</p><a class="button secondary" href="pages.php?page=booking">Оставить учебную заявку ↗</a></div>
  <div class="contact-visit-facts" aria-label="Транспорт и доступность в учебной версии"><article><span>Транспорт</span><strong>Данные не заданы</strong></article><article><span>Парковка</span><strong>Не подтверждена</strong></article><article><span>Доступность</span><strong>Требует проверки</strong></article></div>
</section>
<?php elseif($view==='cart'): ?>
<?php ep_hero('注文','ВАШ ВЫБОР','Корзина','Ваш заказ','Проверьте блюда, способ получения и итог. Демо не списывает оплату и не передаёт заказ ресторану.','assets/generated/cart-hero.webp','Сервировка ужина при свете бумажного фонаря','Продолжить выбор','menu.php','','cart-hero'); ?>
<section class="section cart-page">
  <div class="cart-page-head"><div><div class="kicker">Tokyo Premium · учебная версия</div><h2>Ваш заказ</h2></div><button type="button" class="text-button" id="cart-clear">Очистить корзину</button></div>
  <div class="cart-layout">
    <div class="cart-lines"><div id="cart-page-items" class="cart-page-items" aria-live="polite"></div><p id="cart-page-empty" class="empty-state" hidden>Корзина пуста. Выберите блюдо в меню.</p></div>
    <aside class="cart-checkout">
      <div class="kicker">Сумма · учебные цены</div><h2 id="cart-page-total">0 ₸</h2>
      <section class="tp20-checkout-quote" data-tp-checkout-quote aria-labelledby="tp20-quote-title">
        <div class="kicker">TOKYO PRIVILEGE · РАСЧЁТ</div><h3 id="tp20-quote-title">Предложения к заказу</h3>
        <div class="tp20-inline-form"><label class="sr-only" for="checkout-promo-code">Промокод</label><input id="checkout-promo-code" name="promo_code" form="checkout-form" maxlength="31" autocomplete="off" value="<?=e(preg_match('/^[A-Z0-9][A-Z0-9_-]{2,30}$/i',(string)($_GET['promo']??'')) ? strtoupper((string)$_GET['promo']) : '')?>" placeholder="Введите промокод"><button type="button" class="button secondary" data-tp-quote>Рассчитать</button></div>
        <button type="button" class="tp20-promo-clear" data-tp-promo-clear hidden>Удалить промокод</button>
        <div class="tp20-gift-choice" data-tp-gift-choice hidden><label for="checkout-gift-dish">Подарок по условиям предложения</label><select id="checkout-gift-dish" name="gift_dish_id" form="checkout-form" disabled><option value="">Выберите подарок</option></select><label for="checkout-gift-quantity">Количество подарков</label><input id="checkout-gift-quantity" name="gift_quantity" form="checkout-form" type="number" min="1" max="10" step="1" inputmode="numeric" value="1" disabled></div>
        <?php if(isset($_SESSION['user_id'])): ?><label class="tp20-points-choice">Списать учебные бонусы<input type="number" name="points_to_redeem" form="checkout-form" min="0" step="1" value="0"></label><?php endif ?>
        <p class="form-status" data-tp-quote-status role="status" aria-live="polite">Итоговые скидки и подарки подтверждаются сервером. Цифры карточек меню — оценки до проверки.</p><p class="tp20-quote-total" data-tp-quote-total hidden></p>
      </section>
      <div class="field"><label for="fulfillment">Способ получения · демо</label><select id="fulfillment" name="fulfillment" form="checkout-form"><option value="delivery">Доставка · учебный режим</option><option value="pickup">Самовывоз · учебный режим</option></select></div>
      <div class="field"><label for="payment-method">Способ оплаты</label><select id="payment-method" name="payment_method" form="checkout-form"><option value="upon_receipt">При получении · демо, списания нет</option><?php if($freedompayReady): ?><option value="freedompay">Онлайн картой · FreedomPay<?= $freedompayTestMode?' · тестовый режим':'' ?></option><?php endif ?></select></div>
      <details class="cart-checkout-details"><summary><span>Перейти к оформлению</span><small>Контакты · адрес при доставке</small></summary><div class="cart-checkout-details-body">
        <div class="field"><label for="checkout-name">Имя</label><input form="checkout-form" id="checkout-name" name="name" required maxlength="80" autocomplete="name"></div>
        <div class="field"><label for="checkout-phone">Телефон</label><input form="checkout-form" id="checkout-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel"></div>
        <?php if($savedCheckoutAddresses): ?><div class="field" id="checkout-saved-address-field"><label for="checkout-saved-address">Мой сохранённый адрес</label><select id="checkout-saved-address"><option value="">Ввести другой адрес</option><?php foreach($savedCheckoutAddresses as $savedAddress): ?><option value="<?= (int)$savedAddress['id']?>" data-address="<?=e($savedAddress['address'])?>" <?=!empty($savedAddress['is_default'])?'selected':''?>><?=e($savedAddress['label'])?> · <?=e($savedAddress['address'])?></option><?php endforeach ?></select></div><?php endif ?>
        <div class="field" id="checkout-address-field"><label for="checkout-address">Адрес доставки</label><input form="checkout-form" id="checkout-address" name="address" required maxlength="180" autocomplete="street-address"></div>
        <details class="cart-comment-disclosure"><summary>Добавить пожелание к заказу <span>необязательно</span></summary><div class="field"><label for="checkout-comment">Комментарий</label><textarea form="checkout-form" id="checkout-comment" name="comment" maxlength="300" placeholder="Например, удобное время звонка"></textarea></div></details>
        <form id="checkout-form" data-api="order"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="secret_code" value=""><input type="hidden" name="items" value="[]"><button class="button" type="submit"><?= $freedompayReady?'Оформить заказ ↗':'Сохранить демо-заказ ↗' ?></button><p class="form-status" aria-live="polite"></p></form>
      </div></details>
      <p class="menu-note"><?php if($freedompayReady): ?>Онлайн-оплата проходит на защищённой странице FreedomPay. <?= $freedompayTestMode?'Включён тестовый режим: деньги не списываются.':'Оплата включена только для настроенного PHP-сервера; проверьте реальные сведения ресторана перед публикацией.' ?><?php else: ?>Здесь доступны только учебные заказы: платёжный шлюз не настроен, реальные списания и передача заказа ресторану отключены.<?php endif ?></p>
    </aside>
    <div class="editorial-heading cart-recommendation-heading"><div class="kicker">Для полного вечера</div><h3>Добавьте к заказу</h3></div><div class="dish-grid cart-recommendations" tabindex="0" aria-label="Рекомендуемые блюда — прокручиваемая подборка"><?php foreach($all as $d) ep_card($d); ?></div>
  </div>
</section>
<?php else: ?>
<section class="page-hero"><div class="kicker">TOKYO PREMIUM</div><h1>Страница не найдена</h1><p>Откройте главную или меню.</p><a class="button" href="index.php">Главная ↗</a></section>
<?php endif ?>
</main>
<script>window.DISHES=<?=json_encode($all,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;</script>
<?php footer_html(); ?>
