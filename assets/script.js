document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        // Ganti tipe input dari password ke text atau sebaliknya
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Ganti ikon mata
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
});