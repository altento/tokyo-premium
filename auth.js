// --- ФИНАЛЬНАЯ ВЕРСИЯ AUTH.JS ---

document.addEventListener('DOMContentLoaded', () => {

    // ===============================================
    // ## ЛОГИКА КАСТОМНОГО КУРСОРА ##
    // ===============================================
    const cursor = document.querySelector('.cursor');
    if (cursor) {
        window.addEventListener('mousemove', (e) => {
            cursor.style.transform = `translate(${e.clientX}px, ${e.clientY}px)`;
        });

        document.querySelectorAll('a, button, input, .btn').forEach(el => {
            el.addEventListener('mouseenter', () => cursor.classList.add('grow'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('grow'));
        });
    }

    // ===============================================
    // ## ЛОГИКА ФОРМЫ АВТОРИЗАЦИИ ##
    // ===============================================
    
    // Элементы для переключения форм
    const switchButtons = document.querySelectorAll('.nav .btn');
    const formSignin = document.querySelector('.form-signin');
    const formSignup = document.querySelector('.form-signup');
    const frame = document.querySelector('.frame');
    const signinActive = document.querySelector('.signin-active');
    const signupInactive = document.querySelector('.signup-inactive');
    const forgot = document.querySelector('.forgot');

    // Формы
    const signinForm = document.getElementById('signin-form');
    const signupForm = document.getElementById('signup-form');

    // Сообщения
    const signinMessage = document.getElementById('signin-message');
    const signupMessage = document.getElementById('signup-message');

    // Элементы для анимации успеха
    const successScreen = document.querySelector('.success');
    const nav = document.querySelector('.nav');

    // Переключение между Sign In и Sign Up
    switchButtons.forEach(button => {
        button.addEventListener('click', () => {
            formSignin.classList.toggle('form-signin-left');
            formSignup.classList.toggle('form-signup-left');
            frame.classList.toggle('frame-long');
            signupInactive.classList.toggle('signup-active');
            signinActive.classList.toggle('signin-inactive');
            forgot.classList.toggle('forgot-left');
        });
    });

    // Обработчик для формы РЕГИСТРАЦИИ
    signupForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(signupForm);

        fetch('register.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(data => {
                if (data.includes("успешно")) {
                    nav?.classList.add('nav-up');
                    formSignup?.classList.add('form-signup-down');
                    successScreen?.classList.add('success-left');
                    frame?.classList.add('frame-short');
                } else {
                    if (signupMessage) signupMessage.textContent = data;
                }
            })
            .catch(error => {
                if (signupMessage) signupMessage.textContent = 'Ошибка сети. Попробуйте снова.';
            });
    });

    // Обработчик для формы ВХОДА
    signinForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(signinForm);

        fetch('login.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(data => {
                if (data.includes("успешно")) {
                    if (signinMessage) signinMessage.textContent = data;
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 1500);
                } else {
                    if (signinMessage) signinMessage.textContent = data;
                }
            })
            .catch(error => {
                if (signinMessage) signinMessage.textContent = 'Ошибка сети. Попробуйте снова.';
            });
    });
});