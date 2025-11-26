<?php
// app/controllers/LibrarianController.php

class LibrarianController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDashboardData() {
        $data = [
            'totalBooks' => 'N/A',
            'copiesAvailable' => 'N/A',
            'recentActivity' => []
        ];

        try {
            // 1. Get total distinct books
            $stmt1 = $this->pdo->query("SELECT COUNT(BookID) AS total FROM Book WHERE Status != 'Archived'");
            $stats1 = $stmt1->fetch();
            $data['totalBooks'] = $stats1['total'];

            // 2. Get total copies available
            $stmt2 = $this->pdo->query("
                SELECT COUNT(BC.CopyID) AS available
                FROM Book_Copy BC
                JOIN Book B ON BC.BookID = B.BookID
                WHERE BC.Status = 'Available' AND B.Status != 'Archived'
            ");
            $stats2 = $stmt2->fetch();
            $data['copiesAvailable'] = $stats2['available'] ?? 0;

            // 3. Get recent book management activity
            $sql_activity = "
                SELECT 
                    ML.Timestamp, 
                    ML.ActionType, 
                    B.Title, 
                    U.Name AS UserName
                FROM Management_Log ML
                LEFT JOIN Book B ON ML.BookID = B.BookID
                JOIN Users U ON ML.UserID = U.UserID
                ORDER BY ML.Timestamp DESC
                LIMIT 5
            ";
            $stmt3 = $this->pdo->query($sql_activity);
            $data['recentActivity'] = $stmt3->fetchAll();

        } catch (PDOException $e) {
            error_log("LibrarianController Error: " . $e->getMessage());
        }

        return $data;
    }
}
?>