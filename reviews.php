<?php
declare(strict_types=1);

require_once "database.php";
require_once "auth.php";

/*
|--------------------------------------------------------------------------
| CUSTOMER ACCESS ONLY
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "customer") !== "customer") {
    header("Location: admin/dashboard.php");
    exit;
}

$userId = currentUserId();

if ($userId === null) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| SUBMIT REVIEW
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $csrf = $_POST["csrf_token"] ?? null;

        if (!is_string($csrf) || !verifyCsrfToken($csrf)) {
            throw new RuntimeException(
                "Invalid request. Please refresh the page and try again."
            );
        }

        $rating = filter_var(
            $_POST["rating"] ?? null,
            FILTER_VALIDATE_INT
        );

        $review = trim(
            (string)($_POST["review"] ?? "")
        );

        /*
        | Validate rating
        */
        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new RuntimeException(
                "Please select a rating from 1 to 5 stars."
            );
        }

        /*
        | Validate review
        */
        if ($review === "") {
            throw new RuntimeException(
                "Please write your review."
            );
        }

        if (mb_strlen($review) < 5) {
            throw new RuntimeException(
                "Your review must contain at least 5 characters."
            );
        }

        if (mb_strlen($review) > 1000) {
            throw new RuntimeException(
                "Your review must not exceed 1000 characters."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT REVIEW
        |--------------------------------------------------------------------------
        | Review starts as pending.
        | Admin must approve it before it appears.
        */

        $stmt = $conn->prepare("
            INSERT INTO reviews (
                user_id,
                rating,
                review,
                status
            )
            VALUES (?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $userId,
            $rating,
            $review
        ]);

        $message =
            "Thank you! Your review has been submitted and is waiting for approval.";

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| GET APPROVED REVIEWS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.rating,
        r.review,
        r.created_at,
        u.name
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.user_id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
");

$stmt->execute();

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken = csrfToken();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reviews | BR Bais Rouilo Gaming Cafe</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | REVIEWS PAGE STYLES
        |--------------------------------------------------------------------------
        */

        .reviews-wrapper {
            max-width: 980px;
            margin: 0 auto;
        }

        .review-message {
            padding: 15px 18px;
            margin-bottom: 25px;
            border: 1px solid #39FF14;
            background: rgba(57, 255, 20, 0.08);
            color: #39FF14;
            font-weight: 600;
        }

        .review-error {
            padding: 15px 18px;
            margin-bottom: 25px;
            border: 1px solid #ff3333;
            background: rgba(255, 0, 0, 0.08);
            color: #ff6666;
            font-weight: 600;
        }

        .review-form-box {
            border: 1px solid rgba(57, 255, 20, .5);
            background: rgba(0, 0, 0, .65);
            padding: 35px;
            margin-bottom: 55px;
        }

        .review-form-box h2,
        .reviews-list-title {
            font-family: Orbitron, sans-serif;
            color: #39FF14;
            margin-bottom: 25px;
            letter-spacing: 1px;
        }

        .review-form-box label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #fff;
        }

        .rating-select,
        .review-textarea {
            width: 100%;
            box-sizing: border-box;
            background: #050505;
            color: #fff;
            border: 1px solid rgba(57, 255, 20, .4);
            padding: 13px 15px;
            font-family: Montserrat, sans-serif;
            outline: none;
        }

        .rating-select {
            margin-bottom: 20px;
        }

        .review-textarea {
            min-height: 150px;
            resize: vertical;
            margin-bottom: 20px;
        }

        .rating-select:focus,
        .review-textarea:focus {
            border-color: #39FF14;
            box-shadow: 0 0 8px rgba(57, 255, 20, .2);
        }

        .review-submit {
            display: inline-block;
            border: none;
            background: #39FF14;
            color: #000;
            padding: 13px 25px;
            font-family: Orbitron, sans-serif;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            transition: .2s ease;
        }

        .review-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(57, 255, 20, .35);
        }

        .review-card {
            border: 1px solid rgba(57, 255, 20, .25);
            background: rgba(0, 0, 0, .55);
            padding: 25px;
            margin-bottom: 18px;
        }

        .review-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 12px;
        }

        .review-author {
            font-family: Orbitron, sans-serif;
            color: #fff;
            font-weight: 700;
        }

        .review-date {
            color: rgba(255, 255, 255, .55);
            font-size: 12px;
            margin-top: 5px;
        }

        .review-stars {
            color: #39FF14;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .review-text {
            color: rgba(255, 255, 255, .85);
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .no-reviews {
            text-align: center;
            border: 1px solid rgba(57, 255, 20, .2);
            padding: 35px;
            color: rgba(255, 255, 255, .6);
        }

        @media (max-width: 600px) {

            .review-form-box {
                padding: 22px;
            }

            .review-card-header {
                flex-direction: column;
                gap: 8px;
            }

            .review-stars {
                order: -1;
            }

        }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>


<main class="inner-page">

    <div class="reviews-wrapper">


        <!-- PAGE HEADING -->

        <div class="page-heading">

            <h1>REVIEWS</h1>

            <p>
                Share your experience at BR Bais Rouilo Gaming Cafe.
            </p>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="review-message">

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if ($error !== ""): ?>

            <div class="review-error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <!-- WRITE REVIEW -->

        <section class="review-form-box">

            <h2>WRITE A REVIEW</h2>

            <form
                method="POST"
                action="reviews.php"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >


                <label for="rating">
                    Rating
                </label>

                <select
                    name="rating"
                    id="rating"
                    class="rating-select"
                    required
                >

                    <option value="">
                        Select your rating
                    </option>

                    <option value="5">
                        ★★★★★ — 5 Stars
                    </option>

                    <option value="4">
                        ★★★★☆ — 4 Stars
                    </option>

                    <option value="3">
                        ★★★☆☆ — 3 Stars
                    </option>

                    <option value="2">
                        ★★☆☆☆ — 2 Stars
                    </option>

                    <option value="1">
                        ★☆☆☆☆ — 1 Star
                    </option>

                </select>


                <label for="review">
                    Your Review
                </label>

                <textarea
                    name="review"
                    id="review"
                    class="review-textarea"
                    maxlength="1000"
                    placeholder="Tell us about your experience..."
                    required
                ></textarea>


                <button
                    type="submit"
                    class="review-submit"
                >
                    SUBMIT REVIEW
                </button>

            </form>

        </section>


        <!-- CUSTOMER REVIEWS -->

        <section>

            <h2 class="reviews-list-title">
                WHAT OUR CUSTOMERS SAY
            </h2>


            <?php if (!$reviews): ?>

                <div class="no-reviews">

                    No approved reviews yet.
                    Be the first to share your experience!

                </div>

            <?php else: ?>


                <?php foreach ($reviews as $item): ?>

                    <article class="review-card">


                        <div class="review-card-header">


                            <div>

                                <div class="review-author">

                                    <?= htmlspecialchars(
                                        (string)$item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>


                                <div class="review-date">

                                    <?= htmlspecialchars(
                                        date(
                                            "F j, Y",
                                            strtotime(
                                                (string)$item["created_at"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            </div>


                            <div class="review-stars">

                                <?= str_repeat(
                                    "★",
                                    (int)$item["rating"]
                                ) ?>

                                <?= str_repeat(
                                    "☆",
                                    5 - (int)$item["rating"]
                                ) ?>

                            </div>


                        </div>


                        <div class="review-text">

                            <?= htmlspecialchars(
                                (string)$item["review"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                    </article>

                <?php endforeach; ?>


            <?php endif; ?>


        </section>


    </div>

</main>


</body>

</html>