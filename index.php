<?php
$srv =getenv('srv');;
$srv_domain = getenv('srv_domain');
$srv_login = getenv('srv_login'); 
$srv_password = getenv('srv_password'); 
$dn = getenv('dn');		
$company_name = getenv('companyname');
$filter = "(&(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(|(mail=*)(telephoneNumber=*)))"; //все пользователи с почтой или телефоном, кроме отключенных.
// Значения OU, которые нужно удалить
$valuesToRemove = ['Перенос Папок', 'Не пробрасывать принтеры', 'Разрешить все'];

// Маппинг для замены значений OU (старое => новое)
$replaceMap = [
    'ТС' => 'Москва',
    // добавьте другие замены по необходимости
];
$TableData = [];
$ds=ldap_connect($srv); 
ldap_set_option($ds,LDAP_OPT_REFERRALS, 0);
ldap_set_option($ds,LDAP_OPT_PROTOCOL_VERSION,3);
if ($ds) { 
    $r=ldap_bind($ds,$srv_login,$srv_password);;     
    $sr=ldap_search($ds,$dn ,$filter );   
    $info = ldap_get_entries($ds, $sr); 

    for ($i=0; $i<$info["count"];$i++) { 
        $UserPlace = "-";
        $UserName = "-";
        $UserPosition = "-";
        $UserMail = "-";
        $UserIpPhone = "-";
        $UserHide = $info[$i]["physicaldeliveryofficename"][0];
        if ($UserHide != 'hide') {
            $UserName = $info[$i]["cn"][0];                //Имя пользователя
            $UserPosition = $info[$i]["title"][0];         // Должность
            $UserMail = $info[$i]["mail"][0];              //mail
            if (!$UserMail) $UserMail = "-";               //если нет данных о ящике в AD, то отображаем прочерк
            $UserIpPhone = $info[$i]["telephonenumber"][0];//ip phone
            if (!$UserIpPhone) $UserIpPhone = "-";
            $UserPlace = $info[$i]["distinguishedname"][0];
            preg_match_all('/OU=([^,]+)/', $UserPlace, $places);
            $places = array_slice($places[1], 0, -2);
            // Фильтруем и заменяем значения в массиве
            $places = array_filter(array_map(function($item) use ($valuesToRemove, $replaceMap) {
                // Если значение в списке на удаление - возвращаем null (будет удалено фильтром)
                if (in_array($item, $valuesToRemove)) {
                    return null;
                }
                return $replaceMap[$item] ?? $item;
                // Если значение есть в маппинге замен - заменяем его
                //if (array_key_exists($item, $replaceMap)) {
                //    return $replaceMap[$item];
                //}
                // Иначе оставляем как есть
                //return $item;
            }, $places));

            // Переиндексация массива (если нужны числовые ключи по порядку)
            $places = array_values($places);
            
            $UserPlace = implode (", ", $places);
            array_push($TableData, [$UserName, $UserPosition, $UserMail,$UserIpPhone,$UserPlace]);
        }
    }
    #usort($TableData, fn($a, $b) => $a[1] <=> $b[1]);

    ldap_close($ds); 
    $userscount = count($TableData);
}
else echo "<h4>Unable to connect to LDAP server</h4>"; 
?>

<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты <?=$company_name?></title>
    <link rel='shortcut icon' href='ico.png'>
    
    <!-- Foundation CSS -->
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/foundation/6.4.3/css/foundation.min.css'>
    
    <!-- DataTables CSS -->
    <link rel='stylesheet' href='https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css'>
    <link rel='stylesheet' href='https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css'>
    <link rel='stylesheet' href='https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css'>
    
    <style>
        body {
            padding: 10px;
            background-color: #f8f8f8;
        }
        .grid-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 5px;
        }
        table.dataTable thead th {
            background-color: #2c3840;
            color: white;
        }
        .filter-input {
            width: 100%;
            padding: 3px;
            box-sizing: border-box;
        }
        
        /* Стили для выравнивания элементов управления */
        .dataTables-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 10px;
        #    gap: 10px;
        }
        
        .dataTables-buttons {
            flex: 1;
            min-width: 140px;
        }
        
        .dataTables-length {
            margin: 0 5px;
            min-width: 120px;
        }
        
        .dataTables-filter {
            flex: 1;
            min-width: 150px;
            text-align: right;
        }
        
        .dataTables_filter input {
            margin-left: 0.5em;
            border-radius: 3px;
            padding: 5px;
            border: 1px solid #ddd;
            width: 100%;
            max-width: 200px;
        }
        
        /* Стили для кнопок */
        .dt-button {
            padding: 0.5em 0.7em !important;
            margin-right: 3px !important;
            font-size: 0.8rem !important;
        }
        
        /* Адаптивные стили для мобильных */
        @media screen and (max-width: 767px) {
            .dataTables-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .dataTables-buttons, 
            .dataTables-length, 
            .dataTables-filter {
                width: 98%;
                min-width: auto;
                margin-bottom: 5px;
            }
            
            .dataTables_filter input {
                max-width: 100%;
            }
            
            .dt-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .dt-button {
                flex: 1;
                min-width: calc(50% - 5px);
                margin-right: 0 !important;
            }
            
            h3 {
                font-size: 1.3rem;
                text-align: center;
            }
        }
        
        /* Стили для responsive таблицы */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.7em;
            margin: 0 2px;
        }
        
        .dataTables_wrapper .dataTables_info {
            padding: 0.5em 0;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <div class="grid-x grid-padding-x">
            <div class="cell">
                <h3>Контакты <?=$company_name?></h3>
                <div style="overflow-x:auto;">
                    <table id='myTable' class='display nowrap' style="width:100%">
                        <thead>
                            <tr>
                                <th>Имя </th>
                                <th>Должность </th>
                                <th>Email </th>
                                <th>Вн.Телефон </th>
								<th>Отдел </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($TableData as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user[0]) ?></td>
                                    <td><?= htmlspecialchars($user[1]) ?></td>
                                    <td><a href="mailto:<?= htmlspecialchars($user[2]) ?>"><?= htmlspecialchars($user[2]) ?></a></td>
                                    <td><?= htmlspecialchars($user[3]) ?></td>
                                    <td><?= htmlspecialchars($user[4]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th><input type="text" placeholder="Фильтр имени" class="filter-input"></th>
                                <th><input type="text" placeholder="Фильтр должности" class="filter-input"></th>
                                <th><input type="text" placeholder="Фильтр email" class="filter-input"></th>
                                <th><input type="text" placeholder="Фильтр телефона" class="filter-input"></th>
								<th><input type="text" placeholder="Фильтр отдела" class="filter-input"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    
    <!-- DataTables -->
    <script src='https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'></script>
    
    <!-- DataTables Buttons -->
    <script src='https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js'></script>
    <script src='https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js'></script>
    <script src='https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js'></script>
    
    <!-- DataTables Responsive -->
    <script src='https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js'></script>
    
    <!-- JSZip -->
    <script src='https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'></script>
    
    <!-- PDFMake -->
    <script src='https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js'></script>

    <script>
        $(document).ready(function() {
            var table = $('#myTable').DataTable({
		scrollX: false,
                dom: '<"dataTables-controls"<"dataTables-buttons"B><"dataTables-length"l><"dataTables-filter"f>>rtip',
                pageLength: 25,
                responsive: true,
                buttons: [
                    {
                        extend: 'copy',
                        text: 'Копировать',
                        className: 'button secondary'
                    },
                    {
                        extend: 'excel',
                        text: 'Excel',
                        className: 'button success'
                    },
                    {
                        extend: 'pdf',
                        text: 'PDF',
                        className: 'button alert'
                    },
                    {
                        extend: 'print',
                        text: 'Печать',
                        className: 'button'
                    }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/ru.json'
                },
                initComplete: function() {
                    // Применяем поиск при изменении значений в полях фильтрации
                    this.api().columns().every(function() {
                        var column = this;
                        var header = $(column.header());
                        var input = $('<input/>')
                            .appendTo(header)
                            .on('keyup change', function() {
                                if (column.search() !== this.value) {
                                    column.search(this.value).draw();
                                }
                            });
                    });
                }
            });

            // Удаляем дублирующиеся поля фильтрации
            $('#myTable tfoot').remove();
            
            // Оптимизация для мобильных устройств
            function adjustForMobile() {
                if ($(window).width() < 768) {
                    $('.dataTables-buttons').addClass('mobile-view');
                } else {
                    $('.dataTables-buttons').removeClass('mobile-view');
                }
            }
            
            // Вызываем при загрузке и изменении размера окна
            adjustForMobile();
            $(window).resize(adjustForMobile);
        });
    </script>
</body>
</html>
