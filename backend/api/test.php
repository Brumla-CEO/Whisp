<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

function getHelloWorld() {
    return ["message" => "test"];
}

echo json_encode(getHelloWorld());

