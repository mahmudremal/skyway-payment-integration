<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST");

$Tokenpost = $_GET['token']
?>


<style>
  body {
    background: #3b3a3a;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .loader {
    background: #3b3a3a;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100vh;
  }
  p {
    font-size: 20px;
  }
</style>
<html>

<head>
</head>

<body>
  <div class="loader">
    <p>Please wait for a moment we are processing your data...</p>
  </div>
  <form method="post" name="myForminput" id="myForm" action="https://app.onlinemerchantpayments.com/api/transaction/v1/consentToken">
    <input type="hidden" id="token" name="token" size="250" value="<?php echo $Tokenpost ?>"><br />
    <input type="hidden" value="Hit Token">
  </form>
  <script>
    document.getElementById("myForm").submit();
  </script>
</body>

</html>