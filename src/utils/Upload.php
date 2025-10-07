<?php
class Upload {
    public static function handleProfileImage($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('El archivo es demasiado grande');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . uniqid() . '.' . $extension;
        $uploadPath = __DIR__ . '/../../uploads/profiles/' . $filename;

        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return '/uploads/profiles/' . $filename;
        }

        throw new Exception('Error al subir el archivo');
    }
}
?>
