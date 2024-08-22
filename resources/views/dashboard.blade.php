@include('include.header')

<div class=" bg-white w-full rounded-2xl shadow-lg">
    <div class=" flex justify-between p-3 text-white rounded-t-2xl">
        <div class=" text-xl font-semibold">
            <h4 style="color: black;">Sale Report</h4>
        </div>
        <div>

        </div>
    </div>
    <div class="py-4">
        <form id="saleReportForm" action="/saleReport" method="post" enctype="multipart/form-data">
            @csrf
            <div class="grid gap-4 mb-6 mx-4 md:grid-cols-4">
                <div>
                    <div class="max-w-sm mx-auto">
                        <label for="selec_user" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Select an admin</label>
                        <select id="selec_user" name="admin_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected>Select user</option>
                            @foreach($users as $user)
                            <option value="{{$user->user_id}}">{{$user->username}} ({{$user->user_role}})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <div class="max-w-sm mx-auto">
                        <label for="selec_manager" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Select a manager</label>
                        <select id="selec_manager" name="manager_ids[]" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected>Select manager</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="fromDate" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">From Date</label>
                    <input type="date" id="fromDate" name="fromdate" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="John" required />
                </div>
                <div>
                    <label for="toDate" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">toDate</label>
                    <input type="date" id="toDate" name="todate" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="John" required />
                </div>
                <div></div>
                <div></div>
                <div></div>
                <div class="col-span-4">
                    <div class="text-right">
                        <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">Submit</button>
                    </div>
                </div>
            </div>
        </form>
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3">Total Receipts</th>
                        <th scope="col" class="px-6 py-3">Total Sold</th>
                        <th scope="col" class="px-6 py-3">Winning Receipts</th>
                        <th scope="col" class="px-6 py-3">Winning Total</th>
                        <th scope="col" class="px-6 py-3">PNL</th>
                        <th scope="col" class="px-6 py-3">Commission</th>
                        <th scope="col" class="px-6 py-3">Advance</th>
                        <th scope="col" class="px-6 py-3">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be dynamically appended here -->
                </tbody>
            </table>
        </div>


    </div>
</div>

@include('include.footer')
<script>
    $(document).ready(function() {
        $('#selec_user').on('change', function() {
            var adminId = $(this).val();

            // Clear the manager dropdown
            $('#selec_manager').empty().append('<option selected>Select manager</option>');

            if (adminId) {
                $.ajax({
                    url: '/getManagers/' + adminId, // URL to fetch managers and sellers
                    type: 'GET',
                    success: function(data) {
                        $('#selec_manager').append('<option value="">all</option>');
                        $.each(data, function(key, value) {
                            $('#selec_manager').append('<option value="' + value.id + '">' + value.username + ' (' + value.role + ')</option>');
                        });
                    },
                    error: function(error) {
                        console.log('Error:', error);
                    }
                });
            }
        });
        $('#selec_manager').on('change', function() {
            var selectedValue = $(this).val();

            if (selectedValue == "") {
                // If 'all' is selected, clear the manager IDs array
                $('#selec_manager').val(null); // Clear the selected options
            }
        });
        // Handle form submission and table update
        // Handle form submission and table update
    $('form').on('submit', function(event) {
        event.preventDefault(); // Prevent the default form submission

        var formData = $(this).serialize(); // Serialize form data

        $.ajax({
            url: $(this).attr('action'), // Get form action URL
            type: 'POST',
            data: formData,
            success: function(response) {
                // Check if the response is successful
                if (response.success) {
                    // Clear the table body before appending new rows
                    $('.relative tbody').empty();

                    // Iterate over the response data and append rows
                    $.each(response.data, function(name, details) {
                        $('.relative tbody').append(`
                            <tr>
                                <td class="px-6 py-4">${name}</td>
                                <td class="px-6 py-4">${details.totalReceipts}</td>
                                <td class="px-6 py-4">${details.orderTotalAmount}</td>
                                <td class="px-6 py-4">${details.winningNumbersTotal}</td>
                                <td class="px-6 py-4">${details.winnings}</td>
                                <td class="px-6 py-4">${details.orderTotalAmount-details.winnings}</td>
                                <td class="px-6 py-4">${details.commission}</td>
                                <td class="px-6 py-4">${details.advance}</td>
                                <td class="px-6 py-4">${details.balance}</td>
                            </tr>
                        `);
                    });
                } else {
                    console.log('Error: Data not successfully returned.');
                }
            },
            error: function(error) {
                console.log('Error:', error);
            }
        });
    });
    });
</script>