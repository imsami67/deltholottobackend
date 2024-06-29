<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lotteries Diarias</title>
    <style type="text/css">
        *{
            margin: 0px;
            padding: 0px;
        }
        body{
            font-family: sans-serif;
            font-size: 18px;
        }
        .mainwrp{
            font-size: 20px;
            font-weight: bold;
            text-align: center;
        }
        table{
            margin: 20px auto;
            width: 90%;
        }
        .table1{
            border: 1px solid black;
        }
        .table1 td{
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }
        .height{
            line-height: 35px;
            padding: 3px 5px;
            font-weight: bold;
        }
        .height td{
            padding: 3px 8px;
            font-size: 30px;
        }
        .height1 td{
            padding: 3px 8px;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <center>
        <img src="{{ asset('assets/images/logo.svg') }}" alt="company logo">
    </center>
    <table cellpadding="5px" cellspacing="5px">
        <tr>
            <th width="40%" align="left">Fecha y hora:</th>
            <td>{{ $data['lotteryData']['order_date'] }}</td>
        </tr>
        <tr>
            <th width="40%" align="left">Vendetor:</th>
            <td>vendor name</td>
        </tr>
        <tr>
            <th width="40%" align="left">Cliente</th>
            <td>{{ $data['lotteryData']['client_name'] }}</td>
        </tr>
    </table>
    <table class="table1" cellspacing="0" cellpadding="5px">
        <tr class="height1">
            <td>Numero</td>
            <td></td>
            <td align="right">Pedazos</td>
        </tr>

        @php
            $groupedItems = collect($data['lotteryData']['orderItems'])->groupBy('product_id');
        @endphp

        @foreach ($groupedItems as $productId => $items)
            <tr>
                <td></td>
                <td align="center"><h3>{{ $items->first()['product_name'] }}</h3></td>
                <td></td>
            </tr>
            @foreach ($items as $item)
                <tr class="height">
                    <td>{{ $item['lot_number'] }}</td>
                    <td align="center">--</td>
                    <td align="right">{{ $item['lot_frac'] }}</td>
                </tr>
            @endforeach
        @endforeach

        <tr class="height1">
            <td>Total:</td>
            <td></td>
            <td align="right">Q.{{ $data['lotteryData']['grand_total'] }}</td>
        </tr>
    </table>
    <div class="mainwrp">
        Powered by: <a href="https://thewebconcept.com/">The Web Concept</a>
    </div>
</body>
</html>
