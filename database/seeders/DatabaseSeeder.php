    <?php

    namespace Database\Seeders;

    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Hash;
    use App\Models\User;

    class DatabaseSeeder extends Seeder
    {
        public function run(): void
        {
            // --- Catálogos base ---
            $this->call([
                WarehousesSeeder::class,
                PaymentTypesSeeder::class,
                ShippingRoutesSeeder::class,
                PriceListsSeeder::class,
                CategorySeeder::class,
                ClientsSeeder::class,
                DriversSeeder::class,
                PosRegistersSeeder::class,
            ]);

            // --- Productos y reglas (deben ir ANTES de PriceListItemsSeeder) ---
            $this->call([
                ProductsSeeder::class,
                ProductBomsSeeder::class,
                ProductSubproductRulesSeeder::class,
            ]);

            // --- Ítems de listas de precios (ya existen productos) ---
            $this->call([
                PriceListItemsSeeder::class,
            ]);

            // --- Documentos / transacciones demo ---
            $this->call([
                SalesSeeder::class,
                SaleItemsSeeder::class,
                QuotesSeeder::class,
                QuoteItemsSeeder::class,
                ClientPriceOverridesSeeder::class,
            ]);

            // --- Usuario Admin ---
            User::updateOrCreate(
                ['email' => 'admin@admin.com'],
                ['name' => 'Admin', 'password' => Hash::make('adminadmin')]
            );
        }
    }
