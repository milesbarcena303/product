<?php

class ProductController
{
    public function test() {
        http_response_code(400);
        echo json_encode(['Welcome to my API']);
    }

    public function index()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($products);
    }

    public function store()
    {
        global $pdo;
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $pdo->prepare("INSERT INTO products (product_name, price, quantity, uom) VALUES (?, ?, ?, ?");

        $stmt->execute([$data["product_name"], $data["price"], $data["quantity"], $data["uom"]]);

        echo json_encode(["message" => "Product added successfully"]);
    }

    public function show($params)
    {
        global $pdo;

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $id = $params['id'];

       

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }

        echo json_encode($product);
    }

    public function update($params)
    {
        global $pdo;

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $id = $params['id'];

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request. Request body is empty.']);
            return;
        }

        $setClauses = [];
        $paramsToBind = [];

        foreach ($data as $key => $value) {
            $allowedFields = ['product_name', 'price', 'quantity', 'uom'];
            if (in_array($key, $allowedFields)) {
                $setClauses[] = "$key = ?";
                $paramsToBind[] = $value;
            }
        }

        if (empty($setClauses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request. No valid fields provided for update.']);
            return;
        }

        $sql = "UPDATE products SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $paramsToBind[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsToBind);

        if (in_array('quantity', $data)) {
            $this->updateSalesRecords($id, $data['quantity']);
        }

        echo json_encode(['message' => 'Product updated successfully']);
    }

    public function destroy($params)
    {
        global $pdo;

        if (!isset($params['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $id = $params['id'];


        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['message' => 'Product deleted successfully']);
    }


    public function updateProductQuantity($productId, $quantity)
{
    global $pdo;

    // Verify that the product exists
    $product = $pdo->query("SELECT * FROM products WHERE id = $productId")->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return "Product not found";
    }

    // Check if the updated quantity is greater than the current quantity
    if ($quantity > $product['quantity']) {
        return "Invalid response: Updated quantity exceeds current quantity";
    }

    // Calculate the total price for the sale
    $totalPrice = $product['price'] * $quantity;

    // Insert the sales record into the sales table
    $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity_change, date) VALUES (?, ?, NOW())");
    $stmt->execute([$productId, $quantity]);

    // Update the product quantity (decrement it)
    $newQuantity = $product['quantity'] - $quantity;
    $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");
    $stmt->execute([$newQuantity, $productId]);

    return "Product quantity updated successfully";
}

    public function calculateTotalAmount($productId)
    {
        global $pdo;

        // Retrieve product information
        $product = $pdo->query("SELECT * FROM products WHERE id = $productId")->fetch(PDO::FETCH_ASSOC);

        // Calculate the total amount (quantity * price)
        $totalAmount = $product['quantity'] * $product['price'];

        return $totalAmount;
    }
    private function updateSalesRecords($productId, $quantity)
    {
        global $pdo;

        // Retrieve product information
        $product = $pdo->query("SELECT * FROM products WHERE id = $productId")->fetch(PDO::FETCH_ASSOC);

        // Calculate the total price for the sale
        $totalPrice = $product['price'] * $quantity;

        // Insert the sales record into the sales table
        $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity_sold, price_per_unit, sale_date) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$productId, $quantity, $product['price']]);

        // Update the product quantity (decrement it)
        $newQuantity = $product['quantity'] - $quantity;
        $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $productId]);
    }

    public function generateSalesReport()
    {
        global $pdo;
    
        // Get the current date in 'Y-m-d' format
        $currentDate = date('Y-m-d');
    
        // Query sales records for the current date
        $stmt = $pdo->prepare("SELECT s.product_id, p.product_name, SUM(s.quantity_change * p.price) AS total_sales
                               FROM sales s
                               INNER JOIN products p ON s.product_id = p.id
                               WHERE DATE(s.date) = ?
                               GROUP BY s.product_id, p.product_name");
        $stmt->execute([$currentDate]);
    
        $salesRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
      
        foreach ($salesRecords as $record) {
            $product_id = $record['product_id'];
            $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
           
        }
    
        $report = [
            'total_sales' => array_sum(array_column($salesRecords, 'total_sales')),
            
        ];
    
        echo json_encode($report);
    }
    
    
}


?>
