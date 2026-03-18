<?php
/**
 * Membership Model
 * Handles subscription plans and customer memberships
 */

require_once __DIR__ . '/../config/database.php';

class Membership {
    
    // --- Plans ---

    public static function getPlans($companyId, $activeOnly = true) {
        $pdo = getDBConnection();
        $sql = "SELECT * FROM membership_plans WHERE company_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY price ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public static function getPlan($planId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE plan_id = ?");
        $stmt->execute([$planId]);
        return $stmt->fetch();
    }

    public static function createPlan($companyId, $data) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO membership_plans (company_id, name, description, price, duration_days)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $companyId,
            $data['name'],
            $data['description'],
            $data['price'],
            $data['duration_days']
        ]);
    }
    
    public static function updatePlan($planId, $data) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE membership_plans 
            SET name = ?, description = ?, price = ?, duration_days = ?, is_active = ?
            WHERE plan_id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['duration_days'],
            $data['is_active'],
            $planId
        ]);
    }

    public static function deletePlan($planId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM membership_plans WHERE plan_id = ?");
        return $stmt->execute([$planId]);
    }

    public static function getPlanUsageCount($planId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_memberships WHERE plan_id = ?");
        $stmt->execute([$planId]);
        return $stmt->fetchColumn();
    }

    // --- Subscriptions / Memberships ---

    public static function getActiveMemberships($companyId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT cm.*, c.customer_name, mp.name as plan_name, mp.duration_days
            FROM customer_memberships cm
            JOIN customers c ON cm.customer_id = c.customer_id
            JOIN membership_plans mp ON cm.plan_id = mp.plan_id
            WHERE c.company_id = ? AND cm.status = 'active' AND cm.end_date >= CURDATE()
            ORDER BY cm.end_date ASC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public static function addMembership($customerId, $planId, $startDate = null) {
        $pdo = getDBConnection();
        
        // Get plan details
        $plan = self::getPlan($planId);
        if (!$plan) throw new Exception("Plan not found");

        $start = $startDate ? $startDate : date('Y-m-d');
        $end = date('Y-m-d', strtotime($start . " + " . $plan['duration_days'] . " days"));
        
        $stmt = $pdo->prepare("
            INSERT INTO customer_memberships (customer_id, plan_id, start_date, end_date, price_paid, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        $success = $stmt->execute([
            $customerId,
            $planId,
            $start,
            $end,
            $plan['price']
        ]);
        
        return $success ? $pdo->lastInsertId() : false;
    }

    public static function linkTransaction($membershipId, $transactionId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE customer_memberships SET transaction_id = ? WHERE membership_id = ?");
        return $stmt->execute([$transactionId, $membershipId]);
    }

    public static function cancelMembership($membershipId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE customer_memberships SET status = 'cancelled' WHERE membership_id = ?");
        return $stmt->execute([$membershipId]);
    }

    public static function deleteMembership($membershipId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM customer_memberships WHERE membership_id = ?");
        return $stmt->execute([$membershipId]);
    }
}
