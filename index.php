<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use JetBrains\PhpStorm\Pure;

session_start();
function whatIsHappening()
{
    echo '<h2>$_GET</h2>';
    var_dump($_GET);
    echo '<h2>$_POST</h2>';
    var_dump($_POST);
    echo '<h2>$_COOKIE</h2>';
    var_dump($_COOKIE);
    echo '<h2>$_SESSION</h2>';
    var_dump($_SESSION);
}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

const DELIVERY_REGULAR = 7200;   //expressed in seconds!
const DELIVERY_EXPRESS = 2700;   //expressed in seconds!

const ZIP = [1000, 9999];
const STREET = [1, 9999];
const NAME = [3, 35];

#[Pure] function test_input($input): string
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_NOQUOTES);
    return $input;
}  //Check incoming input


class User
{
    public string $email;
    public string $street;
    public string $street_number;
    public string $city;
    public string $zipcode;
    public array $order;

    #[Pure] public function __construct($email, $street, $street_number, $city, $zipcode, $order)
    {
        foreach ($order as $key => $value) {
            $order[$key] = test_input($value);
        }

        $this->email = test_input($email);
        $this->street = test_input($street);
        $this->street_number = test_input($street_number);
        $this->city = test_input($city);
        $this->zipcode = test_input($zipcode);
        $this->order = $order;

    }
}

class product
{
    public string $name;
    public float $price;

    public function __construct($name, $price)
    {
        $this->name = $name;
        $this->price = $price;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

}

$products = [
    [
        new product('Cola', 2),
        new product('Fanta', 2),
        new product('Sprite', 2),
        new product('Ice-tea', 3),
    ],
    [
        new product('Club Ham', 3.20),
        new product('Club Cheese', 3),
        new product('Club Cheese & Ham', 4),
        new product('Club Chicken', 4),
        new product('Club Salmon', 5),
    ]
];

if (isset($_GET['food'])) {
    $products = match ($_GET['food']) {
        '0' => $products[0],
        '1' => $products[1],
        '2' => array_merge($products[0], $products[1]),
    };
} else {
    $products = $products[0];
}

//Total Spent On Food and Drink
$totalValue = $_COOKIE['totalValue'] ?? '0';

//Obtaining validated data from form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $_SESSION['email'] = $_SESSION['street'] = $_SESSION['street_number'] = $_SESSION['city'] = $_SESSION['zipcode'] = '';

    $client = new User($_POST['email'], $_POST['street'], $_POST['street_number'], $_POST['city'], $_POST['zipcode'], $_POST['products']);
    $errors = [];
    $streetNameLength = strlen($client->street);
    $cityNameLength = strlen($client->city);

    //Validating Costumer Form:

    if (empty($client->email)) {
        $errors[] = 'Email is required';
    } elseif (!(filter_var($client->email, FILTER_VALIDATE_EMAIL))) {
        $errors[] = 'Email is Invalid';
    } else {
        $_SESSION['email'] = $client->email;
    }

    if (empty($client->street)) {
        $errors[] = 'Street is required';
    } elseif ($streetNameLength < NAME[0] || $streetNameLength > NAME[1]) {
        $errors[] = 'Street is Invalid';
    } else {
        $_SESSION['street'] = $client->street;
    }

    if (empty($client->street_number)) {
        $errors[] = 'Street number is required';
    } elseif (!is_numeric($client->street_number) || !filter_var($client->street_number, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => STREET[0],
                'max_range' => STREET[1])))) {
        $errors[] = 'Street number is invalid';
    } else {
        $_SESSION['street_number'] = $client->street_number;
    }

    if (empty($client->city)) {
        $errors[] = 'City is required';
    } elseif ($cityNameLength < NAME[0] || $cityNameLength > NAME[1]) {
        $errors[] = 'City is Invalid';
    } else {
        $_SESSION['city'] = $client->city;
    }

    if (empty($client->zipcode)) {
        $errors[] = 'Zipcode is required';
    } elseif (!is_numeric($client->zipcode) || !filter_var($client->zipcode, FILTER_VALIDATE_INT, array('options' => array('min_range' => ZIP[0],
            'max_range' => ZIP[1])))) {
        $errors[] = 'Zipcode is invalid';
    } else {
        $_SESSION['zipcode'] = $client->zipcode;
    }

    //Validating Placed Order

    $placedOrders = false;
    $validOrders = true;
    $_SESSION['order'] = $client->order;

    foreach ($client->order as $key => $value) {
        if (!empty($value)) {
            $placedOrders = true;
        } else {
            $_SESSION['order'][$key] = '';
        }
        if (!empty($value) && !is_numeric($value)) {
            $validOrders = false;
            $_SESSION['order'][$key] = '';
        }
    }
    if (!$placedOrders) {
        $errors[] = 'No Order placed';
    } elseif (!$validOrders) {
        $errors[] = 'Invalid Order placed';
    }

    $_SESSION['errors'] = $errors;
    $_SESSION['order_placed'] = true;
    $_SESSION['express_delivery'] = $_POST['express_delivery'] ?? '0';

    header('Location: index.php');
    exit;
}

//Act on errors || success

if (isset($_SESSION['order_placed'])) {
    if (isset($_SESSION['errors']) && empty($_SESSION['errors'])) {

        //Confirm Order && Provide Delivery Time && Calc Money spent

        echo '<div class="alert alert-success" role="alert">';
        echo nl2br("Order successfully placed\n");

        //provide delivery time

        $deliverTime = (isset($_POST['express_delivery'])) ? DELIVERY_EXPRESS : DELIVERY_REGULAR;
        $timeOfDelivery = date('H:i', time() + $deliverTime);
        echo nl2br("Drone ETA $timeOfDelivery\n");

        //Calculating Payment:
        $bill = 0;
        $ordered = '';

        if (isset($_SESSION['order'])) {
            foreach ($_SESSION['order'] as $item => $quantity) {
                if (!empty($quantity)) {
                    $subTot = (float)$products[$item]->getPrice() * (int)$quantity;
                    $ordered .= $products[$item]->getName() . ' x ' . $quantity . ' units = € ' . number_format($subTot, 2) . "\n";
                    $bill += $subTot;
                }
            }
        }

        $express_fee = (int)($_SESSION['express_delivery'] ?? '0');
        $paymentOnDelivery = $bill + $express_fee;

        echo nl2br("Ordered items:\n $ordered");
        if (!empty($express_fee)) {
            echo nl2br("Express fee: € $express_fee\n");
        }

        echo nl2br("Total: € " . number_format($paymentOnDelivery, 2) . "\n");
        echo '</div>';

        $totalValue += $bill;
        setcookie('totalValue', (string)$totalValue, ['expires' => time() + 86400,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax']);

    } else {
        echo '<div class="alert alert-warning" role="alert">';
        foreach ($_SESSION['errors'] as $err) {
            echo nl2br("$err\n");
        }
        echo '</div>';
    }
}

unset($_SESSION['order'], $_SESSION['order_placed']);
require 'form-view.php';

