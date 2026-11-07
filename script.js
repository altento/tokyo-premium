// --- НАША БАЗА ДАННЫХ МЕНЮ ---
const menuData = [
    { id: 1, category: 'rolls', image: 'https://images.unsplash.com/photo-1617196034183-421b4917c92d?q=80&w=1887&auto=format&fit=crop', name: 'Ролл "Филадельфия"', weight: '280г', description: 'Нежный лосось, сливочный сыр, огурец, рис, нори.', price: 4500 },
    { id: 2, category: 'rolls', image: 'https://images.unsplash.com/photo-1615361200141-f45040f367be?q=80&w=1964&auto=format&fit=crop', name: 'Ролл "Калифорния"', weight: '260г', description: 'Снежный краб, авокадо, огурец, икра тобико, рис.', price: 4000 },
    { id: 5, category: 'rolls', image: 'https://images.unsplash.com/photo-1649494293780-6d17a60d5438?q=80&w=1470&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Ролл "Зеленый Дракон"', weight: '300г', description: 'Копченый угорь, авокадо, сливочный сыр, соус унаги, кунжут.', price: 5500 },
    { id: 9, category: 'rolls', image: 'https://plus.unsplash.com/premium_photo-1668143358351-b20146dbcc02?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Запеченный ролл "Вулкан"', weight: '320г', description: 'Лосось, огурец, сливочный сыр под шапкой из острого соуса спайси.', price: 5200 },
    { id: 3, category: 'sushi', image: 'https://images.unsplash.com/photo-1636005100045-23f484a6c001?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Суши с лососем', weight: '40г', description: 'Классические нигири-суши со свежайшим лососем.', price: 1200 },
    { id: 6, category: 'sushi', image: 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?q=80&w=1904&auto=format&fit=crop', name: 'Суши с тунцом', weight: '40г', description: 'Нигири-суши с премиальным тунцом Bluefin.', price: 1500 },
    { id: 10, category: 'sushi', image: 'https://images.unsplash.com/photo-1753078947472-c1b4c782f861?q=80&w=1470&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Гункан с икрой', weight: '50г', description: '"Кораблик" из риса и нори, наполненный икрой лосося.', price: 1800 },
    { id: 7, category: 'hot', image: 'https://images.unsplash.com/photo-1563245372-f21724e3856d?q=80&w=1888&auto=format&fit=crop', name: 'Рамен с говядиной', weight: '550г', description: 'Насыщенный говяжий бульон, лапша, мраморная говядина, яйцо адзитама.', price: 4800 },
    { id: 11, category: 'hot', image: 'https://plus.unsplash.com/premium_photo-1667807521884-e25207a0555b?q=80&w=1135&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Удон с курицей терияки', weight: '450г', description: 'Пшеничная лапша удон, обжаренная с курицей и овощами в соусе терияки.', price: 4200 },
    { id: 8, category: 'desserts', image: 'https://plus.unsplash.com/premium_photo-1701104844024-01bd7d26e119?q=80&w=1471&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Десерт "Моти"', weight: '120г', description: 'Традиционный японский десерт из рисового теста с ягодной начинкой.', price: 2800 },
    { id: 4, category: 'drinks', image: 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?q=80&w=1471&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', name: 'Матча латте', weight: '300мл', description: 'Японский зеленый чай матча на кокосовом молоке.', price: 2500 }
];


document.addEventListener('DOMContentLoaded', () => {
    // --- ГЛОБАЛЬНАЯ КОРЗИНА ---
    let cart = JSON.parse(localStorage.getItem('cart')) || {};

    // --- ОБЩАЯ ЛОГИКА ДЛЯ ВСЕХ СТРАНИЦ ---
    const initBaseLogic = () => {
        const cursor = document.querySelector('.cursor');
        if (cursor) {
            window.addEventListener('mousemove', (e) => {
                cursor.style.transform = `translate(${e.clientX}px, ${e.clientY}px)`;
            });
        }

        const header = document.getElementById('header');
        let lastScrollTop = 0;
        if (header) {
            window.addEventListener('scroll', () => {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollTop > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    header.classList.add('header-hidden');
                } else {
                    header.classList.remove('header-hidden');
                }
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            });
        }
        
        const burgerMenu = document.getElementById('burger-menu');
        const mobileNav = document.getElementById('mobile-nav');
        if (burgerMenu && mobileNav) {
            burgerMenu.addEventListener('click', () => {
                burgerMenu.classList.toggle('active');
                mobileNav.classList.toggle('open');
            });
        }

        AOS.init({ duration: 1000, once: true });
    };

    // --- ЛОГИКА ТОЛЬКО ДЛЯ ГЛАВНОЙ СТРАНИЦЫ ---
    const initHomePage = () => {
        const heroTitle = document.querySelector('.hero-title');
        if (heroTitle) {
            heroTitle.querySelectorAll('.word').forEach(word => {
                word.innerHTML = word.textContent.split('').map(letter => `<span class="letter">${letter === ' ' ? '&nbsp;' : letter}</span>`).join('');
            });
            setTimeout(() => {
                heroTitle.querySelectorAll('.letter').forEach((letter, index) => {
                    setTimeout(() => letter.classList.add('visible'), index * 50);
                });
            }, 500);
        }

        const galleryItems = document.querySelectorAll('.gallery-item');
        galleryItems.forEach(item => {
            item.addEventListener('mousemove', (e) => {
                const rect = item.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                item.style.transform = `rotateX(${-y/20}deg) rotateY(${x/20}deg) scale(1.05)`;
            });
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'rotateX(0deg) rotateY(0deg) scale(1)';
            });
        });
    };
    
    // --- ЛОГИКА СИСТЕМЫ БРОНИРОВАНИЯ ---
    const initBookingModal = () => {
        const modal = document.getElementById('booking-modal');
        const openModalBtn = document.querySelector('.hero-cta .btn-primary');
        const closeModalBtn = document.getElementById('close-booking-btn');
        const bookingForm = document.getElementById('booking-form');
        const dateInput = document.getElementById('booking-date');
        const timeSlotsContainer = document.getElementById('time-slots');

        if (!modal || !openModalBtn || !closeModalBtn) return;

        const availableTimes = ["12:00", "13:00", "14:00", "15:00", "18:00", "19:00", "20:00", "21:00"];
        let selectedTime = null;

        const openModal = () => modal.classList.add('is-open');
        const closeModal = () => modal.classList.remove('is-open');

        const generateTimeSlots = (date) => {
             if (!timeSlotsContainer) return;
            timeSlotsContainer.innerHTML = '';
            availableTimes.forEach(time => {
                const isPast = new Date(date + 'T' + time) < new Date();
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'time-slot';
                btn.textContent = time;
                if (isPast) {
                    btn.classList.add('disabled');
                    btn.disabled = true;
                }
                btn.onclick = () => {
                    if (selectedTime) {
                        timeSlotsContainer.querySelector('.selected')?.classList.remove('selected');
                    }
                    selectedTime = time;
                    btn.classList.add('selected');
                };
                timeSlotsContainer.appendChild(btn);
            });
        };
        
        openModalBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            dateInput.min = today;
            generateTimeSlots(today);
            openModal();
        });

        closeModalBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        dateInput.addEventListener('change', () => {
            selectedTime = null;
            generateTimeSlots(dateInput.value);
        });

        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!selectedTime) {
                alert('Пожалуйста, выберите время бронирования.');
                return;
            }
            const guestName = document.getElementById('guest-name').value;
            showSuccessMessage(
                `${guestName}, столик забронирован!`,
                `Мы ждем вас ${dateInput.value} в ${selectedTime}.`
            );
            closeModal();
            bookingForm.reset();
            selectedTime = null;
        });
    };

    // --- ИСПРАВЛЕННАЯ ЛОГИКА КОРЗИНЫ И ЗАКАЗА (ОБЩАЯ) ---
    const initCart = () => {
        const cartIcon = document.getElementById('cart-icon');
        const cartModal = document.getElementById('cart-modal');
        const closeCartBtn = document.getElementById('close-cart-btn');
        const cartItemsContainer = document.getElementById('cart-items-container');
        const cartTotalPriceEl = document.getElementById('cart-total-price');
        const orderForm = document.getElementById('order-form');
        const clearCartBtn = document.getElementById('clear-cart-btn');

        clearCartBtn?.addEventListener('click', () => {
            const isConfirmed = confirm('Вы уверены, что хотите полностью очистить корзину?');
            if (isConfirmed) {
                cart = {};
                updateCartUI();
            }
        });

        const openCart = () => cartModal?.classList.add('is-open');
        const closeCart = () => cartModal?.classList.remove('is-open');
        
        window.updateCartUI = () => {
            localStorage.setItem('cart', JSON.stringify(cart)); 

            const cartCounter = document.getElementById('cart-counter');
            const cartItems = Object.values(cart);
            let totalCount = 0;
            let totalPrice = 0;

            const cartFooter = document.querySelector('.cart-footer');
            if (cartFooter) {
                if (cartItems.length === 0) {
                    if(cartItemsContainer) cartItemsContainer.innerHTML = '<p class="cart-empty-message">Ваша корзина пуста</p>';
                    cartFooter.style.display = 'none';
                } else {
                    cartFooter.style.display = 'block';
                    if(cartItemsContainer) {
                        cartItemsContainer.innerHTML = cartItems.map(item => {
                            totalCount += item.quantity;
                            totalPrice += item.price * item.quantity;
                            return `
                                <div class="cart-item" data-id="${item.id}">
                                    <img src="${item.image}" alt="${item.name}" class="cart-item-img">
                                    <div class="cart-item-details">
                                        <h4>${item.name}</h4>
                                        <span class="item-price">${item.quantity} x ${item.price} KZT</span>
                                    </div>
                                    <div class="cart-item-controls">
                                        <button class="quantity-btn minus">-</button>
                                        <span class="quantity-display">${item.quantity}</span>
                                        <button class="quantity-btn plus">+</button>
                                        <button class="cart-item-remove">&times;</button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                }
            }

            if (cartCounter) {
                cartCounter.textContent = totalCount;
                cartCounter.classList.remove('animate');
                if (totalCount > 0) {
                    void cartCounter.offsetWidth;
                    cartCounter.classList.add('animate');
                }
            }

            if (cartTotalPriceEl) cartTotalPriceEl.textContent = `${totalPrice} KZT`;

            document.querySelectorAll('.menu-item, .item-controls[data-id]').forEach(container => {
                const id = container.dataset.id;
                const itemInCart = cart[id];
                
                const addToCartBtn = container.querySelector('.btn-add-to-cart');
                const quantityControl = container.querySelector('.quantity-control');
                
                if (addToCartBtn && quantityControl) {
                     if (itemInCart) {
                        addToCartBtn.classList.add('hidden');
                        quantityControl.classList.remove('hidden');
                        quantityControl.querySelector('.quantity-display').textContent = itemInCart.quantity;
                    } else {
                        addToCartBtn.classList.remove('hidden');
                        quantityControl.classList.add('hidden');
                    }
                }
            });
        };

        window.changeQuantity = (id, amount) => {
            const itemData = menuData.find(item => item.id == id);
            if (!itemData) return;
            
            if (!cart[id] && amount > 0) {
                cart[id] = { ...itemData, quantity: 1 };
            } else if (cart[id]) {
                cart[id].quantity += amount;
                if (cart[id].quantity <= 0) {
                    delete cart[id];
                }
            }
            updateCartUI();
        };

        cartIcon?.addEventListener('click', openCart);
        closeCartBtn?.addEventListener('click', closeCart);
        cartModal?.addEventListener('click', (e) => { if (e.target === cartModal) closeCart(); });
        
        cartItemsContainer?.addEventListener('click', (e) => {
            const cartItem = e.target.closest('.cart-item');
            if (!cartItem) return;
            const id = cartItem.dataset.id;

            if (e.target.matches('.quantity-btn.plus')) {
                changeQuantity(id, 1);
            }
            if (e.target.matches('.quantity-btn.minus')) {
                changeQuantity(id, -1);
            }
            if (e.target.matches('.cart-item-remove')) {
                if (cart[id]) {
                     changeQuantity(id, -cart[id].quantity);
                }
            }
        });
        
        orderForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const orderName = document.getElementById('order-name').value;
            
            showSuccessMessage(
                `${orderName}, спасибо за заказ!`,
                'Наш менеджер скоро свяжется с вами для подтверждения.'
            );

            closeCart();
            orderForm.reset();
            cart = {};
            updateCartUI();
        });
        
        updateCartUI();
    };

    // --- ЛОГИКА ТОЛЬКО ДЛЯ СТРАНИЦЫ МЕНЮ ---
    const initMenuPage = () => {
        const menuGrid = document.querySelector('.menu-grid');
        const detailsModal = document.getElementById('details-modal');
        const detailsContent = document.getElementById('details-modal-content');

        const createMenuItemHTML = (item) => {
            return `
            <div class="menu-item" data-category="${item.category}" data-id="${item.id}">
                <img src="${item.image}" alt="${item.name}" class="item-image">
                <div class="item-content">
                    <h4 class="item-name">${item.name}</h4>
                    <p class="item-details"><span class="item-weight">${item.weight}</span></p>
                    <p class="item-description">${item.description}</p>
                    <div class="item-footer">
                        <span class="item-price">${item.price}</span>
                        <div class="item-controls">
                           <button class="btn btn-add-to-cart">В корзину</button>
                           <div class="quantity-control hidden">
                              <button class="quantity-btn minus">-</button>
                              <span class="quantity-display">1</span>
                              <button class="quantity-btn plus">+</button>
                           </div>
                        </div>
                    </div>
                </div>
            </div>`;
        };
        
        if (menuGrid) {
            menuGrid.innerHTML = menuData.map(createMenuItemHTML).join('');
        }

        menuGrid?.addEventListener('click', (e) => {
            const itemCard = e.target.closest('.menu-item');
            if (!itemCard) return;
            const id = itemCard.dataset.id;
            
            if (e.target.matches('.btn-add-to-cart, .quantity-btn')) {
                if (e.target.matches('.btn-add-to-cart')) changeQuantity(id, 1);
                if (e.target.matches('.quantity-btn.plus')) changeQuantity(id, 1);
                if (e.target.matches('.quantity-btn.minus')) changeQuantity(id, -1);
            } else if (e.target.matches('.item-image, .item-name')) {
                const itemData = menuData.find(item => item.id == id);
                if (!itemData) return;
                
                detailsContent.innerHTML = `
                    <button class="close-btn" id="close-details-btn">&times;</button>
                    <div class="details-content">
                        <img src="${itemData.image}" alt="${itemData.name}" class="details-img">
                        <div class="details-info">
                            <h3>${itemData.name}</h3>
                            <p>${itemData.description}</p>
                            <p class="item-details">Вес: ${itemData.weight}</p>
                            <p class="item-price">${itemData.price}</p>
                            <div class="item-controls" data-id="${id}">
                                <button class="btn btn-add-to-cart">В корзину</button>
                                <div class="quantity-control hidden"><button class="quantity-btn minus">-</button><span class="quantity-display">1</span><button class="quantity-btn plus">+</button></div>
                            </div>
                        </div>
                    </div>`;
                detailsModal.classList.add('is-open');
                updateCartUI(); 
            }
        });
        
        detailsModal?.addEventListener('click', (e) => { 
            if (e.target === detailsModal || e.target.matches('.close-btn')) {
                detailsModal.classList.remove('is-open');
            }
            const controls = e.target.closest('.item-controls');
            if (controls) {
                const id = controls.dataset.id;
                if (e.target.matches('.btn-add-to-cart')) changeQuantity(id, 1);
                if (e.target.matches('.quantity-btn.plus')) changeQuantity(id, 1);
                if (e.target.matches('.quantity-btn.minus')) changeQuantity(id, -1);
            }
        });

        const filterButtons = document.querySelectorAll('.category-btn');
        const menuItems = document.querySelectorAll('.menu-item');
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const category = button.dataset.category;
                menuItems.forEach(item => {
                    item.style.display = (category === 'all' || item.dataset.category === category) ? 'flex' : 'none';
                });
            });
        });
    };

    // --- ФУНКЦИЯ ДЛЯ ПОКАЗА СООБЩЕНИЙ ОБ УСПЕХЕ ---
    const showSuccessMessage = (title, message) => {
        const successModal = document.getElementById('success-modal');
        if (!successModal) return;
        
        successModal.querySelector('#success-title').textContent = title;
        successModal.querySelector('#success-message').textContent = message;
        successModal.classList.add('is-open');

        const closeSuccessModal = () => successModal.classList.remove('is-open');
        
        successModal.querySelector('#close-success-btn').onclick = closeSuccessModal;
        successModal.onclick = (e) => {
            if (e.target === successModal) closeSuccessModal();
        };
    };

    // --- ИЗМЕНЕНИЕ ЗДЕСЬ: ЛОГИКА ОТОБРАЖЕНИЯ СТАТУСА ПОЛЬЗОВАТЕЛЯ ---
    const updateUserStatus = () => {
        const userActionsContainer = document.querySelector('.user-actions');
        const mobileNav = document.getElementById('mobile-nav');

        if (!userActionsContainer || !mobileNav) return;

        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.loggedIn) {
                    // Пользователь вошел: показываем иконку профиля и кнопку выхода
                    const profileHTML = `
                        <a href="profile.html" class="profile-icon" title="Личный кабинет">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </a>
                        <a href="logout.php" class="logout-link">Выйти</a>
                    `;
                    userActionsContainer.innerHTML = profileHTML;

                    // Обновляем мобильное меню
                    const authLinkMobile = mobileNav.querySelector('a[href="auth.html"]');
                    if (authLinkMobile) {
                        authLinkMobile.href = 'profile.html';
                        authLinkMobile.textContent = 'Личный кабинет';
                    }

                } else {
                    // Пользователь не вошел: оставляем стандартную ссылку
                    userActionsContainer.innerHTML = `<a href="auth.html" class="interactive-element">Войти / Регистрация</a>`;
                    
                    // Возвращаем мобильное меню в исходное состояние
                    const profileLinkMobile = mobileNav.querySelector('a[href="profile.html"]');
                     if (profileLinkMobile) {
                        profileLinkMobile.href = 'auth.html';
                        profileLinkMobile.textContent = 'Войти / Регистрация';
                    }
                }
            })
            .catch(error => console.error('Ошибка проверки сессии:', error));
    };


    // --- ЗАПУСК ВСЕХ СКРИПТОВ ---
    initBaseLogic();
    initCart();
    
    // --- ИЗМЕНЕНИЕ ЗДЕСЬ: Вызываем функцию проверки статуса пользователя ---
    updateUserStatus();
    
    if (document.querySelector('.hero')) {
        initHomePage();
        initBookingModal();
    }
    if (document.querySelector('.menu-page')) {
        initMenuPage();
    }
    
    updateCartUI();
});