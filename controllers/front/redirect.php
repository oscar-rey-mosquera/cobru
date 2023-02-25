<?php


class cobruRedirectModuleFrontController extends ModuleFrontController {
    public function postProcess()
    {
        Tools::redirect('index.php?controller=history?not-payment-in-cobru=😥');

    }

}