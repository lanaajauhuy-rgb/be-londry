<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $customer_code
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $address
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCustomerCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 */
	class Customer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string $address
 * @property string|null $scheduled_at
 * @property string|null $completed_at
 * @property int|null $courier_user_id
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $courier
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCourierUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delivery whereUpdatedAt($value)
 */
	class Delivery extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $order_number
 * @property int $customer_id
 * @property int|null $received_by_user_id
 * @property string|null $outlet_name
 * @property string $order_date
 * @property string|null $estimated_done_at
 * @property string|null $completed_at
 * @property string $status
 * @property string $payment_status
 * @property numeric $subtotal
 * @property numeric $total_amount
 * @property numeric $amount_paid
 * @property numeric $amount_due
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAmountDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEstimatedDoneAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOutletName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereReceivedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int $service_id
 * @property string $service_type
 * @property string|null $item_name
 * @property int $qty
 * @property numeric|null $weight_kg
 * @property numeric $unit_price
 * @property numeric $line_total
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereLineTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereServiceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereWeightKg($value)
 */
	class OrderItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $pricing_model
 * @property numeric $unit_price
 * @property int|null $estimated_hours
 * @property string|null $description
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereEstimatedHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service wherePricingModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUpdatedAt($value)
 */
	class Service extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string|null $phone
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

