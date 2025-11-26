<?php
// app/controllers/StaffController.php

class StaffController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDashboardData() {
        // Initialize default return data (handles potential errors gracefully)
        $data = [
            'pendingRequests' => 0,
            'outstandingPenalties' => 0,
            'recentActivity' => []
        ];

        try {
            // 1. Get total pending borrowing requests (Status='Reserved')
            $stmt1 = $this->pdo->query("SELECT COUNT(BorrowID) AS pending FROM Borrow WHERE Status = 'Reserved'");
            $stats1 = $stmt1->fetch();
            $data['pendingRequests'] = $stats1['pending'] ?? 0;

            // 2. Get total outstanding (pending) penalties
            $stmt2 = $this->pdo->query("SELECT COUNT(PenaltyID) AS outstanding FROM Penalty WHERE Status = 'Pending'");
            $stats2 = $stmt2->fetch();
            $data['outstandingPenalties'] = $stats2['outstanding'] ?? 0;

            // 3. Get recent operational activity (Borrow/Return)
            $sql_activity = "
                SELECT 
                    BR.ActionTimestamp, 
                    BR.ActionType, 
                    BK.Title, 
                    U.Name AS UserName
                FROM Borrowing_Record BR
                JOIN Borrow BO ON BR.BorrowID = BO.BorrowID
                JOIN Users U ON BO.UserID = U.UserID
                JOIN Book_Copy BCPY ON BO.CopyID = BCPY.CopyID
                JOIN Book BK ON BCPY.BookID = BK.BookID
                WHERE BR.ActionType IN ('Borrowed', 'Returned')
                ORDER BY BR.ActionTimestamp DESC
                LIMIT 5
            ";
            $stmt3 = $this->pdo->query($sql_activity);
            $data['recentActivity'] = $stmt3->fetchAll();

        } catch (PDOException $e) {
            error_log("StaffController Error: " . $e->getMessage());
            // Return default data on error
        }

        return $data;
    }
}
?>