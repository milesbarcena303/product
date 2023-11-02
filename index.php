<?php

require_once "db.php";
require_once "Router.php";
require_once "ProductController.php";

header("Content-Type: application/json");

$router = new Router();
$productController = new ProductController($pdo);

$router->get('/products', [$productController, 'index']);
$router->post('/products', [$productController, 'store']);
$router->get('/products/{id}', [$productController, 'show']);
$router->put('/products/{id}', [$productController, 'update']);
$router->delete('/products/{id}', [$productController, 'destroy']);
$router->get('/', [$productController, 'test']);

// Update product quantity
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $product_id = $_GET['product_id'];
    $quantity = $_GET['quantity'];
    $result = $productController->updateProductQuantity($product_id, $quantity);
    echo json_encode(['message' => $result]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report']) && $_GET['report'] === 'true') {
    $salesReport = $productController->generateSalesReport();

    // Calculate the total amount for each product in the sales report
    foreach ($salesReport as &$product) {
        $product_id = $product['product_id'];
        $product['total_amount'] = $productController->calculateTotalAmount($product_id);
    }

    $response = [
        'sales_report' => $salesReport,
    ];

    echo json_encode($response);
} 

else {
    
    $router->handleRequest();
}
?>