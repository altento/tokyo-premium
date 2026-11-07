// Файл: profile.js

document.addEventListener('DOMContentLoaded', () => {
    
    // Функция для запроса и отображения данных
    const loadProfileData = () => {
        fetch('get_profile_data.php')
            .then(response => {
                if (!response.ok) {
                    // Если пользователь не авторизован (сервер ответил ошибкой 401),
                    // перенаправляем его на страницу входа.
                    window.location.href = 'auth.html';
                    throw new Error('Not authorized');
                }
                return response.json();
            })
            .then(data => {
    if (!data.userInfo) {
        console.error('Профиль: userInfo отсутствует в ответе', data);
        alert('Не удалось загрузить ваш профиль. Пожалуйста, войдите заново.');
        window.location.href = 'auth.html';
        return;
    }
                // Если данные успешно получены, вызываем функции для их отображения
                displayUserInfo(data.userInfo);
                displayUpcomingBookings(data.upcomingBookings);
                displayOrderHistory(data.orderHistory);
                displayBookingHistory(data.bookingHistory);
            })
            .catch(error => {
                console.error('Ошибка при загрузке данных профиля:', error);
            });
    };

    // Отображение информации о пользователе
    const displayUserInfo = (user) => {
        document.getElementById('welcome-message').textContent = `Добро пожаловать, ${user.username}!`;
        document.getElementById('info-username').textContent = user.username;
        document.getElementById('info-email').textContent = user.email;
        const regDate = new Date(user.registration_date).toLocaleDateString('ru-RU');
        document.getElementById('info-regdate').textContent = regDate;
    };

    // Отображение предстоящих бронирований
    const displayUpcomingBookings = (bookings) => {
        const container = document.getElementById('upcoming-bookings-list');
        if (!bookings || bookings.length === 0) {
            container.innerHTML = '<div class="empty-list-message">У вас нет предстоящих бронирований.</div>';
            return;
        }
        container.innerHTML = bookings.map(b => `
            <div class="booking-card">
                <span><strong>Дата:</strong> ${new Date(b.booking_date).toLocaleDateString('ru-RU')} в ${b.booking_time.slice(0, 5)}</span>
                <span><strong>Гостей:</strong> ${b.guest_count}</span>
                <span style="color: #4CAF50;"><strong>Статус:</strong> Подтверждено</span>
            </div>
        `).join('');
    };
    
    // Отображение истории заказов (в виде "аккордеона")
    const displayOrderHistory = (orders) => {
        const container = document.getElementById('order-history-list');
        if (!orders || orders.length === 0) {
            container.innerHTML = '<div class="empty-list-message">Вы еще ничего не заказывали.</div>';
            return;
        }
        container.innerHTML = orders.map(order => `
            <div class="order-card">
                <div class="order-card-header order-summary">
                    <span><strong>Заказ #${order.id}</strong> от ${new Date(order.order_date).toLocaleDateString('ru-RU')}</span>
                    <span><strong>Сумма:</strong> ${parseFloat(order.total_price).toFixed(0)} KZT</span>
                </div>
                <div class="order-details">
                    <ul>
                        ${order.items.map(item => `<li><span>${item.item_name} (x${item.quantity})</span> <span>${(item.price * item.quantity).toFixed(0)} KZT</span></li>`).join('')}
                    </ul>
                </div>
            </div>
        `).join('');

        // Добавляем логику, чтобы по клику раскрывался список блюд
        container.querySelectorAll('.order-summary').forEach(summary => {
            summary.addEventListener('click', () => {
                const details = summary.nextElementSibling;
                details.style.display = details.style.display === 'block' ? 'none' : 'block';
            });
        });
    };

    // Отображение истории бронирований
    const displayBookingHistory = (bookings) => {
         const container = document.getElementById('booking-history-list');
        if (!bookings || bookings.length === 0) {
            container.innerHTML = '<div class="empty-list-message">История бронирований пуста.</div>';
            return;
        }
        container.innerHTML = bookings.map(b => `
            <div class="booking-card">
                <span><strong>Дата:</strong> ${new Date(b.booking_date).toLocaleDateString('ru-RU')} в ${b.booking_time.slice(0, 5)}</span>
                <span><strong>Гостей:</strong> ${b.guest_count}</span>
                <span style="color: #9E9E9E;"><strong>Статус:</strong> Завершено</span>
            </div>
        `).join('');
    };

    // Запускаем загрузку всех данных, как только страница открылась
    loadProfileData();
});