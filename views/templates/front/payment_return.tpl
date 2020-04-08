{extends "$layout"}

{block name="content"}
  <section>
    

  <!--   <p>{l s='You Will now be redirected to PayBeagle to complete Your Payment.'}</p> -->

    <!-- START PAYBEAGLE INTEGRATION -->
    <div id="payBeagleHostedPlugin"></div>
    <script type="text/javascript" src="https://{$params['pb_platform_url']}/static/hosted.js"></script>
    <script type="text/javascript">
    var payment = new PayBeagle({
      'type'  :'IFRAME',
      'userID'  :'{$params['pb_user']}',
      'userPassword'  :'{$params['pb_pass']}',
      'amount'  :'{$params['total']|string_format:"%.2f"}',
      'orderRef'  :'{$params['cart_id']}',
      'orderDescription'  :"Payment For cart id {$params['cart_id']}"
    });
    payment.execute();
    </script>
    <noscript>Sorry, you must enable javascript to use our payment system</noscript>
    <!-- END PAYBEAGLE INTEGRATION -->


    <!-- <p>{l s='Here are the params:'}</p>
    <ul>
      {foreach from=$params key=name item=value}
        <li>{$name}: {$value}</li>
      {/foreach}
    </ul>
    
    

    <p>{l s="Now, you just need to proceed the payment and do what you need to do."}</p> -->
  </section>
{/block}
