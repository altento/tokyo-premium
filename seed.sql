INSERT OR IGNORE INTO categories(slug,name,sort_order) VALUES ('rolls','Роллы',1),('sushi','Суши и сашими',2),('hot','Горячее',3),('desserts','Десерты',4),('drinks','Напитки',5);
INSERT OR IGNORE INTO dishes(id,category,name,description,weight,price,image_url,featured,active,sort_order) VALUES
(1,'rolls','Филадельфия','Нежный лосось, сливочный сыр, огурец, рис, нори.','280 г',4500,'https://images.unsplash.com/photo-1617196034183-421b4917c92d?q=80&w=1200&auto=format&fit=crop',1,1,1),
(2,'rolls','Калифорния','Снежный краб, авокадо, огурец, икра тобико, рис.','260 г',4000,'https://images.unsplash.com/photo-1615361200141-f45040f367be?q=80&w=1200&auto=format&fit=crop',1,1,2),
(5,'rolls','Зелёный дракон','Копчёный угорь, авокадо, сливочный сыр, соус унаги, кунжут.','300 г',5500,'https://images.unsplash.com/photo-1649494293780-6d17a60d5438?q=80&w=1200&auto=format&fit=crop',0,1,3),
(9,'rolls','Запечённый ролл «Вулкан»','Лосось, огурец и сливочный сыр под шапкой из соуса спайси.','320 г',5200,'https://plus.unsplash.com/premium_photo-1668143358351-b20146dbcc02?q=80&w=1200&auto=format&fit=crop',0,1,4),
(3,'sushi','Нигири с лососем','Классические нигири с лососем.','40 г',1200,'https://images.unsplash.com/photo-1636005100045-23f484a6c001?q=80&w=1200&auto=format&fit=crop',0,1,1),
(6,'sushi','Нигири с тунцом','Нигири с тунцом.','40 г',1500,'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?q=80&w=1200&auto=format&fit=crop',0,1,2),
(10,'sushi','Гункан с икрой','Рис и нори, наполненные икрой лосося.','50 г',1800,'https://images.unsplash.com/photo-1753078947472-c1b4c782f861?q=80&w=1200&auto=format&fit=crop',0,1,3),
(7,'hot','Рамен с говядиной','Говяжий бульон, лапша, говядина и яйцо адзитама.','550 г',4800,'https://images.unsplash.com/photo-1563245372-f21724e3856d?q=80&w=1200&auto=format&fit=crop',1,1,1),
(11,'hot','Удон с курицей терияки','Пшеничная лапша удон, курица и овощи в соусе терияки.','450 г',4200,'https://plus.unsplash.com/premium_photo-1667807521884-e25207a0555b?q=80&w=1200&auto=format&fit=crop',0,1,2),
(8,'desserts','Моти','Рисовое тесто с ягодной начинкой.','120 г',2800,'https://plus.unsplash.com/premium_photo-1701104844024-01bd7d26e119?q=80&w=1200&auto=format&fit=crop',0,1,1),
(4,'drinks','Матча латте','Японский чай матча на кокосовом молоке.','300 мл',2500,'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1200&auto=format&fit=crop',0,1,1);
INSERT OR IGNORE INTO categories(slug,name,sort_order) VALUES ('starters','Закуски',1),('soups','Супы',4),('sets','Сеты',7);
INSERT OR IGNORE INTO dishes(id,category,name,description,weight,price,image_url,featured,active,sort_order) VALUES
(12,'starters','Тартар из тунца с юдзу','Тунец ахи, понзу с юдзу, зелёное яблоко и хрустящий рис — прохладная закуска с чистым цитрусовым финалом.','140 г',4900,'https://images.unsplash.com/photo-1547592180-85f173990554?q=80&w=1200&auto=format&fit=crop',0,1,1),
(13,'starters','Гёдза с уткой','Тонкое тесто, томлёная утка, имбирь и кунжутное масло. Подаём с соусом понзу собственного состава.','6 шт.',4600,'https://images.unsplash.com/photo-1585032226651-759b368d7246?q=80&w=1200&auto=format&fit=crop',0,1,2),
(14,'soups','Мисо-суп с вакаме','Светлый бульон даси, паста сира-мисо, тофу, вакаме и зелёный лук.','300 мл',2400,'https://images.unsplash.com/photo-1547592180-85f173990554?q=80&w=1200&auto=format&fit=crop',0,1,1),
(15,'hot','Эби темпура','Тигровые креветки в лёгком тесте, сезонные овощи и тэнцую с дайконом.','240 г',5600,'https://images.unsplash.com/photo-1583623025817-d180a2221d0a?q=80&w=1200&auto=format&fit=crop',1,1,3),
(16,'hot','Донбури с угрём унаги','Рис косихикари, угорь с глазурью кабаяки, тамаго и маринованный дайкон.','420 г',6900,'https://images.unsplash.com/photo-1563245372-f21724e3856d?q=80&w=1200&auto=format&fit=crop',0,1,4),
(17,'sets','Омакасе · 18 кусочков','Сет из роллов, нигири и сезонной позиции, собранный в балансе вкусов от лёгкого к насыщенному.','510 г',13900,'https://images.unsplash.com/photo-1611143669185-af224c5e3252?q=80&w=1200&auto=format&fit=crop',1,1,1),
(18,'sets','Сет «Ханами»','Филадельфия, калифорния, нигири с лососем и тёплый ролл «Вулкан» для вечера на двоих.','780 г',19800,'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?q=80&w=1200&auto=format&fit=crop',0,1,2),
(19,'desserts','Матча-тирамису','Крем маскарпоне, савоярди с ходзича и пудра из церемониальной матчи.','170 г',3600,'https://images.unsplash.com/photo-1578985545062-69928b1d9587?q=80&w=1200&auto=format&fit=crop',1,1,2),
(20,'drinks','Юдзу-лимонад','Домашняя цитрусовая основа юдзу, содовая и тонкая нота свежего имбиря.','300 мл',2300,'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1200&auto=format&fit=crop',0,1,2);
INSERT OR IGNORE INTO settings(key,value) VALUES
('site_name','Tokyo Premium'),('meta_title','Tokyo Premium — японская кухня и искусство момента'),('meta_description','Сезонная японская кухня, выверенная подача и внимание к каждому гостю. Изучите меню и оставьте заявку на столик.'),('hero_eyebrow','ЯПОНСКАЯ КУХНЯ · РИТУАЛ ВКУСА'),('hero_title','Тишина, в которой слышно вкус'),('hero_text','Сезонные ингредиенты, точность ремесла и японское гостеприимство — в каждом жесте.'),('address','Астана · точное расположение подтвердим при бронировании'),('phone','Связаться с командой можно через форму бронирования'),('hours','Бронирование доступно через форму на сайте'),('instagram','');

UPDATE settings SET value='Астана, улица Сакура, 17 · квартал Ханами' WHERE key='address' AND value='Астана · точное расположение подтвердим при бронировании';
UPDATE settings SET value='+7 (7172) 55-18-26' WHERE key='phone' AND value='Связаться с командой можно через форму бронирования';
UPDATE settings SET value='Вт–Вс · 12:00–23:00 · Понедельник — выходной' WHERE key='hours' AND value='Бронирование доступно через форму на сайте';
UPDATE settings SET value='Tokyo Premium — японский ресторан и омакасе в Астане' WHERE key='meta_title' AND value='Tokyo Premium — японская кухня и искусство момента';
UPDATE settings SET value='Ресторан японской кухни Tokyo Premium в Астане: сезонное меню, сет омакасе и внимательное гостеприимство. Бронируйте стол онлайн.' WHERE key='meta_description' AND value='Сезонная японская кухня, выверенная подача и внимание к каждому гостю. Изучите меню и оставьте заявку на столик.';
UPDATE settings SET value='АСТАНА · ЯПОНСКАЯ КУХНЯ · С 2022 ГОДА' WHERE key='hero_eyebrow' AND value='ЯПОНСКАЯ КУХНЯ · РИТУАЛ ВКУСА';
UPDATE settings SET value='Авторский японский стол, сезонные продукты и ритуал гостеприимства omotenashi — в самом сердце квартала Ханами.' WHERE key='hero_text' AND value='Сезонные ингредиенты, точность ремесла и японское гостеприимство — в каждом жесте.';
INSERT OR IGNORE INTO reviews(id,guest_name,rating,body,status,created_at) VALUES (1,'Ая',5,'Нигири подали при нужной температуре, а юдзу в тартаре звучал очень тонко. Ужин запомнился именно деталями.','published','2025-11-12 19:40:00'),(2,'Данияр',5,'Брали сет «Ханами» на двоих. Понравилось, что у роллов разные текстуры и вкус не перегружен соусами. Отдельная радость — чай в конце вечера.','published','2025-12-03 20:15:00'),(3,'Мира',5,'Очень красивый зал и внимательная подача. Матча-тирамису оказался нежным, не приторным. Вернусь за омакасе.','published','2026-01-18 18:05:00'),(4,'Ерлан',4,'Заказывали донбури с угрём и гёдза. Всё приехало тёплым, рис сохранил текстуру, а соус был отдельно — это удобно.','published','2026-02-07 21:10:00');
UPDATE categories SET sort_order=1 WHERE slug='starters';
UPDATE categories SET sort_order=2 WHERE slug='rolls';
UPDATE categories SET sort_order=3 WHERE slug='sushi';
UPDATE categories SET sort_order=4 WHERE slug='soups';
UPDATE categories SET sort_order=5 WHERE slug='hot';
UPDATE categories SET sort_order=6 WHERE slug='sets';
UPDATE categories SET sort_order=7 WHERE slug='desserts';
UPDATE categories SET sort_order=8 WHERE slug='drinks';
