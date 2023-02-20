<?php
/**
 * Webshop CRUD style data structure (Sample)
 */

class Webshop
{

    /** Connect to DB */
    private static function connectDb()
    {
        try {
            $pdo = new PDO('mysql:dbname=webshop;host=localhost', 'root', '');
            $pdo->exec("SET CHARACTER SET UTF8");
            $pdo->exec("SET NAMES UTF8");
            return $pdo;
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }

    /** Creation of DB tables */
    public static function createTables()
    {
        $pdo = self::connectDb();
        
        try {
            // Products (product)
            /**
             * prodId: Product Id
             * prodCode: Product code or name
             * prodQuantTotal: Product total quantity on stock (updated automatically according to purchases and sells)
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS products (
            prodId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prodCode VARCHAR(255) NOT NULL,
            prodQuantTotal INT(5) NOT NULL DEFAULT 0
        )
        CHARACTER SET utf8 COLLATE utf8_general_ci;");

            // Product price (price)
            /**
             * priceId: Price Id
             * prodId: Product Id
             * netPrice: Price without tax
             * VAT: Value Added Tax (in per cent, in INT or decimal formats e.g 27)
             * discount: Discount (in per cent, in INT or DECIMAL format if applicable)
             * totalPrice: price amount with tax and discount (calculated and saved/updated automatically)
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS price (
            priceId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prodId INT(5) NOT NULL,
            netPrice DECIMAL(6, 2) NOT NULL,
            VAT INT(2) NOT NULL DEFAULT 0,
            discount INT(2) NOT NULL DEFAULT 0,
            totalPrice DECIMAL(7, 2) NOT NULL
            )
            CHARACTER SET utf8 COLLATE utf8_general_ci;");

            // Purchase of products (purchase)
            /**
             * purchId: Purchase Id
             * prodId: Product Id
             * prodQuant: Total quantity of purchased products
             * partner: ID of provider/partner
             * purchDate: Date of purchase
             * invNum: Number of invoice
             * itemNetprice: Price without tax of one item
             * VAT: Value Added Tax (in per cent, in INT or DECIMAL formats e.g 27)
             * discount: Discount (in per cent, in INT or DECIMAL format if applicable)
             * itemTotalPrice: price amount with tax and discount of one item (calculated and saved automatically)
             * shipmentTotalPrice: total cost of the purchase (calculated and saved automatically)
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS purchase (
            purchId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prodId INT(5) NOT NULL,
            prodQuant INT(5) NOT NULL,
            partner INT(5) NOT NULL,
            purchDate DATE NOT NULL,
            invNum VARCHAR(255) NOT NULL,
            itemNetPrice DECIMAL(6, 2) NOT NULL,
            VAT INT(2) NOT NULL DEFAULT 0,
            discount INT(2) NOT NULL DEFAULT 0,            
            itemTotalPrice DECIMAL(7, 2) NOT NULL,
            shipmentTotalPrice DECIMAL(7, 2) NOT NULL
        )
        CHARACTER SET utf8 COLLATE utf8_general_ci;");

            // Warranty (warranty)
            /**
             * warrId: ID of warranty
             * warrName: Name of warranty
             * warrTimeSpan: Validity of warranty (time)
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS warranty (
            warrId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,            
            warrName VARCHAR(255) NOT NULL,
            warrTimeSpan INT(3) NOT NULL
        )
        CHARACTER SET utf8 COLLATE utf8_general_ci;");

            // Sold Items and Claims of Warranty (soldItem)
            /**
             * sId: Id of transaction
             * prodId: Id of sold product
             * prodQuant: Quantity of sold items
             * invNum: Number of invoice
             * sDate: Date of transaction
             * sTotalPrice: Total amount of transaction (calculated and saved automatically)
             * warrId: Id of warranty, if applicable
             * warrStat: Status of warranty (e.g inactive, active, claimed)
             * warrQuant: Quantity of products returned according to the warranty, if it is applicable
             * warrStartDate: Start date of the validity of the warranty
             * warrEndDate: End date of the validity of the warranty (calculated and saved/updated automatically)
             * warrDate: Date of the warranty claim, it it is applicable
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS soldItem (
            sId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prodId INT(5) NOT NULL,
            prodQuant INT(3) NOT NULL,
            invNum VARCHAR(255) NOT NULL,
            sDate DATE NOT NULL,                       
            sTotalPrice DECIMAL(7, 2) NOT NULL,
            warrId INT(5) NOT NULL DEFAULT 0,
            warrStat INT(1) NOT NULL DEFAULT 0,
            warrQuant INT(5) NOT NULL DEFAULT 0,
            warrStartDate DATE,
            warrEndDate DATE,
            warrDate DATE
        )
        CHARACTER SET utf8 COLLATE utf8_general_ci;");

            //Partners (partners)
            /**
             * partnerId: Id of the partner
             * partnerName: Name of the partner
             * partnerAddress: Address of the partner
             * partnerEmail: E-mail of the partner
             * partnerPhone: Telephone of the partner
             */
            $pdo->query("CREATE TABLE IF NOT EXISTS partners (
            partnerId INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            partnerName VARCHAR(255) NOT NULL,
            partnerAddress VARCHAR(500) NOT NULL,
            partnerEmail VARCHAR(255) NOT NULL,
            partnerPhone VARCHAR(50) NOT NULL
        )
        CHARACTER SET utf8 COLLATE utf8_general_ci;");
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }

    /** Data management methods */

    // Filter user input
    private static function filterUserInput($input)
    {
        return htmlentities(trim($input));
    }
    
    // Save data (Create)
    public static function saveData($table, $data)
    {
        $validation = true;

        // Validation of partner's e-mail
        if ($table === 'partners') {
            
            // Validation of partner e-mail
            if (filter_var($data['partnerEmail'], FILTER_VALIDATE_EMAIL)) {
                $validation = true;
            } else {
                echo 'Please, provide a valid e-mail address!';
                $validation = false;
            }
        }

        if ($validation) {
            $pdo = self::connectDb();
            $columns = $tokens = $values = [];

            // Setting of values according to tables to be saved in.
            switch ($table) {

                case 'price': // Calculation of totalPrice according to the VAT and discount ammounts (Table price)
                    $vat = $data['VAT'] != 0 ? $data['netPrice'] * number_format($data['VAT'] / 100, 2, '.', '') : 0;
                    $discount = $data['discount'] != 0 ? ($data['netPrice'] + $vat) * round($data['discount'] / 100, 2) : 0;
                    
                    if ($vat != 0 && $discount == 0) {
                        $totalPrice = $data['netPrice'] + (float)$vat;
                    } elseif ($vat == 0 && $discount != 0) {
                        $totalPrice = $data['netPrice'] - (float)$discount;
                    } elseif ($vat != 0 && $discount != 0) {
                        $totalPrice = ($data['netPrice'] + (float)$vat) - (float)$discount;
                    }
                    $data['totalPrice'] = $totalPrice;
                    
                    foreach ($data as $column => $value) {
                        array_push($columns, $column);
                        array_push($values, self::filterUserInput($value));
                    }
                    $columnNames = implode(', ', $columns);
                    $tokens = implode(', ', str_replace(array_values($columns), '?', $columns));
                break;

                case 'purchase': // Table purchase
                    // Updating product total quantity in products table (adding the total ammount of purchased items)
                    $productQuant = self::getData('products', 'prodQuantTotal', ['prodId' => $data['prodId']], false, false, false, 1);
                    $productQuantUpdate = $productQuant[0]['prodQuantTotal'] + $data['prodQuant'];
                    self::updateData('products', ['prodQuantTotal' => $productQuantUpdate], 'prodId', [$data['prodId']], 1);
                    
                    // Calculation of itemTotalPrice based on VAT and discounts
                    $vat = $data['VAT'] != 0 ? $data['itemNetPrice'] * round($data['VAT'] / 100, 2) : 0;
                    $discount = $data['discount'] != 0 ? ($data['itemNetPrice'] + $vat) * round($data['discount'] / 100, 2) : 0;

                    if ($vat != 0 && $discount == 0) {
                        $itemTotalPrice = round($data['itemNetPrice'] + $vat, 2);
                    } elseif ($vat == 0 && $discount != 0) {
                        $itemTotalPrice = round($data['itemNetPrice'] - $discount, 2);
                    } elseif ($vat != 0 && $discount != 0) {
                        $itemTotalPrice = round(($data['itemNetPrice'] + $vat) - $discount, 2);
                    }

                    $data['itemTotalPrice'] = $itemTotalPrice;

                    // Calculation of shipmentTotalPrice based on itemTotalPrice and quantity (prodQuant)
                    $data['shipmentTotalPrice'] = round($itemTotalPrice * $data['prodQuant'], 2);
                    
                    foreach ($data as $column => $value) {
                        array_push($columns, $column);
                        array_push($values, self::filterUserInput($value));
                    }
                    $columnNames = implode(', ', $columns);
                    $tokens = implode(', ', str_replace(array_values($columns), '?', $columns));
                break;

                case 'soldItem': // Table soldItem
                    // Updating product total quantity in products table
                    $productQuant = self::getData('products', 'prodQuantTotal', ['prodId' => $data['prodId']], false, false, false, 1);
                    $productQuantUpdate = $productQuant[0]['prodQuantTotal'] - $data['prodQuant'];
                    self::updateData('products', ['prodQuantTotal' => $productQuantUpdate], 'prodId', [$data['prodId']], 1);

                    // Calculation of the transaction's total price (sTotalPrice)
                    $productPrice = self::getData('price', 'totalPrice', ['prodId' => $data['prodId']], false, false, false, 1);
                    $data['sTotalPrice'] = round($productPrice[0]['totalPrice'] * $data['prodQuant'], 2);

                    // Determining Warranty eligibility and length
                    if ($data['warrStat'] == 1) {
                        $warranty = self::getData('warranty', 'warrTimeSpan', ['warrId' => $data['warrId']], false, false, 1);
                        $warrantyLength = $warranty[0]['warrTimeSpan'];
                        $date = date_create($data['warrStartDate']);
                        date_add($date, date_interval_create_from_date_string("$warrantyLength months"));
                        $data['warrEndDate'] = date_format($date, 'Y-m-d');
                    }
                    
                    foreach ($data as $column => $value) {
                        array_push($columns, $column);
                        array_push($values, self::filterUserInput($value));
                    }
                    $columnNames = implode(', ', $columns);
                    $tokens = implode(', ', str_replace(array_values($columns), '?', $columns));
                break;

                default:
                
                foreach ($data as $column => $value) {
                    array_push($columns, $column);
                    array_push($values, self::filterUserInput($value));
                }
                $columnNames = implode(', ', $columns);
                $tokens = implode(', ', str_replace(array_values($columns), '?', $columns));
            break;
            }
            
            try {
                $query = $pdo->prepare("INSERT INTO $table ($columnNames) VALUES($tokens)");
                $save = $query->execute($values);
                echo($save) ? 'Success!' : 'Error! Please, try again!';
            } catch (PDOException $e) {
                echo 'Error: ' . $e->getMessage();
            }
        }
    }
    
    // Obtaining data (Read)
    public static function getData($table, $values = false, $where = [], $whereCon = false, $whereCustom = false, $orderBy = false, $limit = false)
    {
        try {
            $pdo = self::connectDB();
            $whereValues = '';
            
            // Assessing WHERE clause
            if (!empty($where)) {
                $whereValues = array_values($where);
                
                if (count($where) > 1) {
                    $tokens = [];
                
                    foreach ($where as $column => $value) {
                        array_push($tokens, "$column = ?");
                    }

                    if ($whereCon && $whereCon === 'AND') {
                        $where = implode(' AND ', $tokens);
                    } elseif ($whereCon && $whereCon === 'OR') {
                        $where = implode(' OR ', $tokens);
                    }
                } else {
                    foreach ($where as $column => $value) {
                        $where = "$column = ?";
                    }
                }
            } elseif ($whereCustom) {
                $where = $whereCustom;
            }

            // Building query
            switch (true) {

            case $values && $where && $orderBy:
                $query = $limit ? "SELECT $values FROM $table WHERE $where ORDER BY $orderBy LIMIT $limit" : "SELECT $values FROM $table WHERE $where ORDER BY $orderBy";
            break;

            case $values && $where:
                $query = $limit ? "SELECT $values FROM $table WHERE $where LIMIT $limit" : "SELECT $values FROM $table WHERE $where";
            break;

            case $where && $orderBy:
                $query = $limit ? "SELECT * FROM $table WHERE $where ORDER BY $orderBy LIMIT $limit" : "SELECT * FROM $table WHERE $where ORDER BY $orderBy LIMIT $limit";
            break;

            case $where:
                $query = $limit ? "SELECT * FROM $table WHERE $where LIMIT $limit" : "SELECT * FROM $table WHERE $where";
            break;

            case $orderBy:
                $query = $limit ? "SELECT * FROM $table ORDER BY $orderBy LIMIT $limit" : "SELECT * FROM $table ORDER BY $orderBy";
            break;

            default:
                $query = "SELECT * FROM $table";
            break;
            }
        } catch (Exception $e) {
            echo($e->getMessage());
        }

        try {
            // Running query
            if (!empty($whereValues)) {
                $dbQuery = $pdo->prepare($query);
                $dbQuery->execute($whereValues);
            } else {
                $dbQuery = $pdo->query($query);
            }
            
            $dbQuery->setFetchMode(PDO::FETCH_ASSOC);
            $dataSet = $dbQuery->fetchAll();

            // Getting product name/code and adding it to the dataSet array, if the table is not 'products'.
            if ($table !== 'products') {
                $productName = $pdo->query("SELECT prodCode FROM products WHERE prodId = $whereValues[0] LIMIT 1");
                $productName->setFetchMode(PDO::FETCH_ASSOC);
                $productNameRes = $productName->fetchAll();
                $dataSet[0]['productName'] = $productNameRes[0]['prodCode'];
            }

            return($dataSet);
        } catch (PDOException $e) {
            echo 'Error:' . $e->getMessage();
            return false;
        }
        $pdo = null;
    }

    // Updating data (Update)
    public static function updateData($table, $values = [], $columnName = false, $where = false, $limit = false)
    {
        $validation = true;
        
        // Validation of partner's e-mail (table partners)    
        if ($table === 'partners') {
            if (filter_var($values['partnerEmail'], FILTER_VALIDATE_EMAIL)) {
                $validation = true;
            } else {
                echo 'Please, provide a valid e-mail address!';
                $validation = false;
            }
        }

        if ($validation) {

            // Assessing Where clause
            if ($where && is_array($where)) {
                $whereClause = implode(', ', $where);
            } else {
                $whereClause = $where;
            }

            try {
                $pdo = self::connectDB();
                $updateValues = [];

                // Assessing values
                if (is_array($values)) {
                    $tokens = [];

                    foreach ($values as $column => $value) {
                        array_push($tokens, "$column = ?");
                        array_push($updateValues, self::filterUserInput($value));
                        
                        switch ($table) {

                            case 'warranty':
                            // Updating warranty end dates in soldItem table in case of updating the warrTimeSpan value of the warranty table
                            if ($column === 'warrTimeSpan') {
                                if (is_array($where)) {
                                    foreach ($where as $wId) {
                                        $soldItems = self::getData('soldItem', 'warrStartDate', ['warrId' => self::filterUserInput($wId)]);
                                    }

                                    foreach ($soldItems as $item) {
                                        $date = date_create($item['warrStartDate']);
                                        date_add($date, date_interval_create_from_date_string("$value months"));
                                        $newWarrLength = $pdo->prepare("UPDATE soldItem SET warrEndDate = ? WHERE warrId = '$wId'");
                                        $newWarrLength->execute([date_format($date, 'Y-m-d')]);
                                    }
                                } else {
                                    $warrLengthArray = explode('=', $where);
                                    $wId = $warrLengthArray[0];
                                    $soldItems = self::getData('soldItem', 'warrStartDate', ['warrId' => $wId]);

                                    foreach ($soldItems as $item) {
                                        $date = date_create($item['warrStartDate']);
                                        date_add($date, date_interval_create_from_date_string("$value months"));
                                        $newWarrLength = $pdo->prepare("UPDATE soldItem SET warrEndDate = ? WHERE warrId = '$wId'");
                                        $newWarrLength->execute([date_format($date, 'Y-m-d')]);
                                    }
                                }
                            }
                        break;

                        case 'price':
                        // Updating totalPrice if any of its parts is altered
                            $totalPrice = '';
                            $priceParts = self::getData('price', 'netPrice, VAT, discount', false, false, "$columnName IN $whereClause");
                            
                            if ($column === 'netPrice' || $column === 'VAT' || $column === 'discount') {
                                if ($column === 'netPrice') {
                                    $vat = $priceParts[0]['VAT'] != 0 ? $value * number_format($priceParts[0]['VAT'] / 100, 2, '.', '') : 0;
                                    $discount = $priceParts[0]['discount'] != 0 ? ($value + $vat) * round($priceParts[0]['discount'] / 100, 2) : 0;

                                    if ($vat != 0 && $discount == 0) {
                                        $totalPrice = $value + (float)$vat;
                                    } elseif ($vat == 0 && $discount != 0) {
                                        $totalPrice = $value - (float)$discount;
                                    } elseif ($vat != 0 && $discount != 0) {
                                        $totalPrice = ($value + (float)$vat) - (float)$discount;
                                    }
                                } elseif ($column === 'VAT') {
                                    $netPrice = $priceParts[0]['netPrice'];
                                    $vat = $value != 0 ? $priceParts[0]['netPrice'] * number_format($value / 100, 2, '.', '') : 0;
                                    $discount = $priceParts[0]['discount'] != 0 ? ($netPrice + $value) * round($priceParts[0]['discount'] / 100, 2) : 0;

                                    if ($vat != 0 && $discount == 0) {
                                        $totalPrice = $netPrice + (float)$vat;
                                    } elseif ($vat == 0 && $discount != 0) {
                                        $totalPrice = $netPrice - (float)$discount;
                                    } elseif ($vat != 0 && $discount != 0) {
                                        $totalPrice = ($netPrice + (float)$vat) - (float)$discount;
                                    }
                                } elseif ($column === 'discount') {
                                    $netPrice = $priceParts[0]['netPrice'];
                                    $vat = $priceParts[0]['VAT'] != 0 ? $netPrice * number_format($priceParts[0]['VAT'] / 100, 2, '.', '') : 0;
                                    $discount = $value != 0 ? ($netPrice + $vat) * round($value / 100, 2) : 0;

                                    if ($vat != 0 && $discount == 0) {
                                        $totalPrice = $netPrice + (float)$vat;
                                    } elseif ($vat == 0 && $discount != 0) {
                                        $totalPrice = $netPrice - (float)$discount;
                                    } elseif ($vat != 0 && $discount != 0) {
                                        $totalPrice = ($netPrice + (float)$vat) - (float)$discount;
                                    }
                                }
                            }

                            if (!empty($totalPrice)) {
                                array_push($tokens, 'totalPrice = ?');
                                array_push($updateValues, $totalPrice);
                            }
                        break;
                        }
                    }
                    if (count($values) > 1) {
                        $tokenSet = implode(', ', $tokens);
                    } else {
                        foreach ($values as $column => $value) {
                            $tokenSet = "$column = ?";
                        }
                    }
                }

                // Building query
                switch (true) {

            case $where:
                $query = $limit ? "UPDATE $table SET $tokenSet WHERE $columnName IN ($whereClause) LIMIT $limit" : "UPDATE $table SET $values WHERE $columnName IN ($whereClause)";
            break;
            
            default:
                $query = $limit ? "UPDATE $table SET $tokenSet LIMIT $limit" : "UPDATE $table SET $values";
            break;
            }
            } catch (Exception $e) {
                echo 'Error: ' . $e->getMessage();
            }

            try {
                // Running query
                $dbQuery = $pdo->prepare($query);
                $update = $dbQuery->execute($updateValues);

                if ($update) {
                    echo 'Success!';
                } else {
                    echo 'Error! Please, try again!';
                }
            } catch (PDOException $e) {
                echo 'Error: '  . $e->getMessage();
                return false;
            }
            $pdo = null;
        }
    }

    // Delete data (Delete)
    public static function deleteData($table, $column = false, $where = [], $limit = false)
    {
        try {
            $pdo = self::connectDB();
            
            if (count($where) > 0) {
                if (count($where) > 1) {
                    $tokens = implode(', ', str_replace(array_values($where), '?', $where));
                    $whereClause = "$column IN ($tokens)";
                } else {
                    $whereClause = "$column = ?";
                }

                // Deleting product prices from price table in case of a removal of the regarding product.
                if ($table === 'products' && $column === 'prodId') {
                    $delPrices = $pdo->prepare("DELETE FROM price WHERE $whereClause");
                    $delPrices->execute($where);
                }
            }
                        
            // Building query
            switch (true) {

                case $where:
                    $query = $limit ? "DELETE FROM $table WHERE $whereClause LIMIT $limit" : "DELETE FROM $table WHERE $whereClause";
                break;
                
                default:
                    $query = $limit ? "DELETE FROM $table LIMIT $limit" : "DELETE FROM $table";
                break;
            }
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        // Running query
        try {
            $dbQuery = $pdo->prepare($query);
            $delete = $dbQuery->execute($where);
            
            if ($delete) {
                echo 'Success!';
            } else {
                echo 'Error! Please, try again!';
            }
        } catch (PDOException $e) {
            echo 'Error:' . $e->getMessage();
            return false;
        }
        $pdo = null;
    }
}
