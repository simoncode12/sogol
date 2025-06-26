(function() {
    // Dapatkan parameter dari URL script itu sendiri
    const scriptTag = document.currentScript;
    const params = new URLSearchParams(scriptTag.src.split('?')[1]);
    const zoneId = params.get('zone_id');
    const width = params.get('w');
    const height = params.get('h');

    if (!zoneId) return;

    // Temukan div target
    const targetDiv = document.getElementById(`adstart-zone-${zoneId}`);
    if (!targetDiv) return;

    // Buat iframe
    const iframe = document.createElement('iframe');
    iframe.width = width || '300';
    iframe.height = height || '250';
    iframe.style.border = '0';
    iframe.style.margin = '0';
    iframe.scrolling = 'no';
    
    // Bangun URL untuk memanggil ad server kita
    const adServerUrl = `https://adstart.click/rtb.php?zone_id=${zoneId}&w=${width}&h=${height}`;
    iframe.src = adServerUrl;

    // Tampilkan iframe di dalam div
    targetDiv.innerHTML = '';
    targetDiv.appendChild(iframe);
})();