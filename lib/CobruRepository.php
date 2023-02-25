<?php


class CobruRepository {

    public static function findOrderStates() {
        global $cookie;
        return OrderState::getOrderStates($cookie->id_lang);

    }

    public static function exitOrderState($id) {
       return OrderState::existsInDatabase($id);
    }
}
