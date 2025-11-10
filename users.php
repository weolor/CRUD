<?php
header('Content-Type: application/json');

$host = 'localhost';
$db = 'my_app';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];  
$script_name = $_SERVER['SCRIPT_NAME'];    
$path = str_replace($script_name, '', $request_uri);
$path = trim($path, '/'); 
$id = is_numeric($path) ? intval($path) : null;

switch($method) {
    case 'GET':
        if ($id) {
            $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            echo json_encode($result ? $result : ['error' => 'User not found']);
        } else {
            $result = $conn->query("SELECT id, username, email FROM users");
            $users = [];
            while($row = $result->fetch_assoc()) $users[] = $row;
            echo json_encode($users);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['username'], $data['email'], $data['password'])) {
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $data['username'], $data['email'], $password);
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
            } else {
                http_response_code(400);
                echo json_encode(['error'=>'Failed to create user']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error'=>'Missing parameters']);
        }
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing user ID']); exit; }
        $data = json_decode(file_get_contents("php://input"), true);
        $fields = []; $types=''; $values=[];
        if (isset($data['username'])) { $fields[]='username=?'; $types.='s'; $values[]=$data['username']; }
        if (isset($data['email'])) { $fields[]='email=?'; $types.='s'; $values[]=$data['email']; }
        if (isset($data['password'])) { $fields[]='password=?'; $types.='s'; $values[]=password_hash($data['password'], PASSWORD_DEFAULT); }

        if ($fields) {
            $sql = "UPDATE users SET ".implode(', ', $fields)." WHERE id=?";
            $types.='i'; $values[]=$id;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) echo json_encode(['success'=>true]);
            else { http_response_code(400); echo json_encode(['error'=>'Failed to update user']); }
        } else {
            http_response_code(400);
            echo json_encode(['error'=>'No fields to update']);
        }
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing user ID']); exit; }
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i",$id);
        if ($stmt->execute()) echo json_encode(['success'=>true]);
        else { http_response_code(400); echo json_encode(['error'=>'Failed to delete user']); }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error'=>'Method not allowed']);
        break;
}

$conn->close();
?>
