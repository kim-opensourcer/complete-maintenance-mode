<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[title]</title>
    <meta name="description" content="[description]">
    [head]
</head>
<body>
    <div class="container">
        <div class="clock-wrapper">
            <div class="clock">🕐</div>
            <div class="time" id="current-time"></div>
        </div>
        <div class="content-wrapper">
            <h1 class="heading">[heading1]</h1>
            <p class="content">[content]</p>
            <div class="social-icons">[social-icons]</div>
        </div>
    </div>
    [footer]
    <script>document.getElementById('current-time').textContent = new Date().toLocaleTimeString(); setInterval(() => document.getElementById('current-time').textContent = new Date().toLocaleTimeString(), 1000);</script>
</body>
</html>
