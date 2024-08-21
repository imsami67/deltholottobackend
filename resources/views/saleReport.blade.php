<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sale Report</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
    <div class="row">
        <div class="col-12">
            <h2>Sale Report</h2>
            <p>Date: {{ $data[array_key_first($data)]['date'] }}</p>
        </div>
    </div>

    @foreach ($data as $username => $userData)
        <div class="row">
            <div class="col-12">
                <h3>User: {{ $username }}</h3>
                <p>Lottery Name: @foreach ($userData['lotteryName'] as $name)
                                {{ $name }},
                            @endforeach</p>
                <table class="table table-bordered table-hover">
                    <tr>
                        <th>Total Receipts</th>
                        <td>{{ $userData['totalReceipts'] }}</td>
                        <th> Total Sold</th>
                        <td>{{ $userData['orderTotalAmount'] }}</td>
                    </tr>
                    <tr>
                        <th>Winnings Receipts</th>
                        <td>{{ $userData['winningNumbersTotal'] }}</td>
                        <th>Winning Total</th>
                        <td>{{ $userData['winnings'] }}</td>
                    </tr>
                    <tr>
                        <th>PNL</th>
                        <td>{{ $userData['totalSold'] }}</td>
                        <th>Commission</th>
                        <td>{{ number_format($userData['commission'], 2) }}</td>
                    </tr>
                    <tr>
                        <th>Advance</th>
                        <td>{{ number_format($userData['advance'], 2) }}</td>
                        <th>Balance</th>
                        <td>{{ number_format($userData['balance'], 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    @endforeach
</div>



    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
