<?php
function formatTanggal($date) {
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];

    $tanggal = new DateTime($date);
    $nama_hari = $hari[$tanggal->format('l')];
    $nama_bulan = $bulan[$tanggal->format('F')];
    
    return $nama_hari . ', ' . $tanggal->format('d') . ' ' . $nama_bulan . ' ' . $tanggal->format('Y');
}
