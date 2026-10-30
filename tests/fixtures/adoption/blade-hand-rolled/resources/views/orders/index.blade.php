<x-app-layout>
    <h1>Orders</h1>
    <x-action-message>Saved.</x-action-message>
    <form method="POST" action="{{ route('orders.store') }}">
        @csrf
        <label for="customer">Customer</label>
        <input id="customer" name="customer" type="text">
        <textarea name="notes"></textarea>
        <button type="submit">Save</button>
    </form>
    <table>
        <thead>
            <tr><th>Customer</th><th>Total</th></tr>
        </thead>
        <tbody>
            <tr><td>Northwind Farms</td><td>120.00</td></tr>
        </tbody>
    </table>
</x-app-layout>
