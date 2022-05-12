<?php
   if (!isset($_POST["stripeToken"])) {
      header("Content-Type:application/json");
      header("HTTP/1.1 403 Forbidden");
      echo json_encode([
         "success"=>false,
         "msg"=>"Invalid request, HTTP POST only allowed!"
      ]);
      return;
   }

   //validate form data
   $stripeToken = $_POST['stripeToken'];
   $name_on_card = $_POST['nameOnCard'];
   $grand_total = $_POST['grandTotal'];
   $products = $_POST['products'];
   $phone = $_POST['phone'];
   $email = $_POST['email'];
   $subscription_price_id = $_POST['subscriptionPriceId'];

   if($stripeToken == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Invalid request, Stripe token was missing! please try again later."
      ]);
      return;
   }

   if($name_on_card == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Name on card is required"
      ]);
      return;
   }
   if($phone == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Phone is required"
      ]);
      return;
   }
   if($email == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Email is required"
      ]);
      return;
   }
   if($grand_total == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Grand total amount is required"
      ]);
      return;
   }
   if($subscription_price_id == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Subscription price id is required"
      ]);
      return;
   }

   if($products == ""){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Products data is required"
      ]);
      return;
   }

   //decrypt grand total amount
   $ciphering = "AES-128-CTR";
     
   // Use OpenSSl Encryption method
   $iv_length = openssl_cipher_iv_length($ciphering);
   $options = 0;
          
   // Store the encryption key
   $encryption_key = "afalkfjdlskafjjfalsdkfjklsdaf!865689-fjadklfjdf-0=fjasdfjd";
     
   // Non-NULL Initialization Vector for decryption
   $decryption_iv = '1234567891011121';

   // Use openssl_decrypt() function to decrypt the data
   $grand_total = openssl_decrypt ($grand_total, $ciphering, 
           $encryption_key, $options, $decryption_iv);

   if (!is_numeric($grand_total) && !$grand_total > 0) {
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"The grand total amount is invalid, please refresh the page and try again"
      ]);
      return;
   }

   //Load Stripe
   require_once('../vendor/autoload.php');
   $stripe = new \Stripe\StripeClient('set_your_secret_key'); //secret key
   $connected_acc_id = ""; //your stripe connected account id
   $subscriptionProcessingFee = 3.50;//percentage

   //First create a customer
   $customer = $stripe->customers->create([
      'name'=>$name_on_card,
      "phone"=>$phone,
      'description' =>"Customer created during checkout and subscription.",
      'email' => $email,
      "source" => $stripeToken
   ]);

   if(!isset($customer["id"]) || $customer["id"] == ''){
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Creating customer on Stripe has been failed, please try again later."
      ]);
      return;
   }

   //now create checkout intent
   $productsData = NULL;
   foreach($products as $key=>$prod){
      $key_ = "product_".($key+1);
      $productsData[$key_] = "Title : ".
                              $prod["title"].", Price: ".
                              $prod["price"].", Quantity: ".
                              $prod["qty"];
   }
   $paymentIntentsCheckout = $stripe->paymentIntents->create([
      "customer"=>$customer->id,
      "metadata"=>$productsData,
      "amount"=>$grand_total * 100,//convert to cents
      "currency"=>"gbp",
      "payment_method_types"=>["card"],
      "description"=>"Collected payments on behalf of MyApp.com"
   ]);

   //now create subscription first
   $subscription = $stripe->subscriptions->create([
     'customer' => $customer->id,
     'items' => [
       ['price' => $subscription_price_id],
     ],
     "expand" => ["latest_invoice.payment_intent"],
     "application_fee_percent"=>$subscriptionProcessingFee,
     "transfer_data" => [
       "destination" => $connected_acc_id
     ],
   ]);

   if (!isset($subscription["id"]) || $subscription["id"] == '' || $subscription["status"] !== "active") {
      header("Content-Type:application/json");
      header("HTTP/1.1 400 Bad Request");
      echo json_encode([
         "success"=>false,
         "msg"=>"Creating subscription on Stripe has been failed, please try again later."
      ]);
      return;
   }



   //send back the checkout token
   header("Content-Type:application/json");
   header("HTTP/1.1 200 OK");
   echo json_encode([
      "checkout_client_secret"=>$paymentIntentsCheckout->client_secret,
      "customer"=>$customer,
      "subscription"=>$subscription
   ]);
   return;
