document.addEventListener("DOMContentLoaded", function () {

    /* ========================================
       MOBILE MENU
    ======================================== */

    const menuToggle = document.querySelector(".menu-toggle");
    const nav = document.querySelector(".main-nav");

    if (menuToggle && nav) {

        menuToggle.addEventListener("click", function () {

            const isOpen = nav.classList.toggle("open");

            menuToggle.setAttribute(
                "aria-expanded",
                isOpen ? "true" : "false"
            );

        });


        const navLinks = nav.querySelectorAll("a");

        navLinks.forEach(function (link) {

            link.addEventListener("click", function () {

                nav.classList.remove("open");

                menuToggle.setAttribute(
                    "aria-expanded",
                    "false"
                );

            });

        });

    }


    /* ========================================
       SCROLL REVEAL
    ======================================== */

    if ("IntersectionObserver" in window) {

        const observer = new IntersectionObserver(
            function (entries) {

                entries.forEach(function (entry) {

                    if (entry.isIntersecting) {

                        entry.target.classList.add("show");

                        observer.unobserve(
                            entry.target
                        );

                    }

                });

            },
            {
                threshold: 0.12
            }
        );


        document
            .querySelectorAll(".reveal")
            .forEach(function (element) {

                observer.observe(element);

            });

    } else {

        document
            .querySelectorAll(".reveal")
            .forEach(function (element) {

                element.classList.add("show");

            });

    }


    /* ========================================
       GALLERY LIGHTBOX
    ======================================== */

    const galleryItems =
        document.querySelectorAll(".gallery-item");


    galleryItems.forEach(function (item) {

        item.addEventListener("click", function () {

            const img = item.querySelector("img");

            if (!img) return;


            const imageSource =
                img.getAttribute("src");

            const imageAlt =
                img.getAttribute("alt");


            const overlay =
                document.createElement("div");

            overlay.className = "lightbox";


            overlay.innerHTML = `
                <button
                    type="button"
                    class="lightbox-close"
                    aria-label="Close gallery"
                >
                    ×
                </button>

                <img
                    src="${imageSource}"
                    alt="${imageAlt}"
                >
            `;


            document.body.appendChild(
                overlay
            );


            requestAnimationFrame(function () {

                overlay.classList.add("open");

            });


            function closeLightbox() {

                overlay.classList.remove("open");

                setTimeout(function () {

                    overlay.remove();

                }, 200);

            }


            overlay.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target === overlay ||
                        event.target.classList.contains(
                            "lightbox-close"
                        )
                    ) {

                        closeLightbox();

                    }

                }
            );


            document.addEventListener(
                "keydown",
                function escapeKey(event) {

                    if (event.key === "Escape") {

                        closeLightbox();

                        document.removeEventListener(
                            "keydown",
                            escapeKey
                        );

                    }

                }
            );

        });

    });


    /* ========================================
       BOOKING DATE
    ======================================== */

    const dateInput =
        document.querySelector(
            'input[name="booking_date"]'
        );


    if (dateInput) {

        const today =
            new Date()
                .toISOString()
                .split("T")[0];


        dateInput.min = today;

    }

});