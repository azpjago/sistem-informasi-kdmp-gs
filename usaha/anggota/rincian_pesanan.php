<!-- rincian_pesanan.html -->
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincian Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-3">
        <h4 class="mb-3">Rincian Pesanan</h4>


        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Beras 5kg <span>Rp 60.000</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Minyak Goreng 2L <span>Rp 28.000</span>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <strong>Total</strong><strong>Rp 88.000</strong>
            </li>
        </ul>


        <div class="alert alert-warning text-center">
            Batalkan pesanan dalam waktu: <strong id="timer">00:30:00</strong>
        </div>


        <button class="btn btn-danger w-100">Batalkan Pesanan</button>
    </div>


    <script>
        let countdown = 30 * 60; // 30 minutes in seconds
        const timerEl = document.getElementById('timer');


        const updateTimer = () => {
            const minutes = Math.floor(countdown / 60);
            const seconds = countdown % 60;
            timerEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            if (countdown > 0) {
                countdown--;
                setTimeout(updateTimer, 1000);
            }
        };


        updateTimer();
    </script>
</body>

</html>