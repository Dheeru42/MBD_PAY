<footer class="mbd-footer">

    <div class="footer-content">

        <div class="footer-logo">
            ₹ MBD Pay
        </div>


        <div class="footer-text">

            © <?php echo date("Y"); ?> MBD Pay. All Rights Reserved.

        </div>


        <div class="footer-links">

            <a href="#">Privacy</a>

            <a href="#">Security</a>

            <a href="#">Support</a>

        </div>


    </div>

</footer>


<style>
    /* ================= FOOTER ================= */


    .mbd-footer {


        position: fixed;

        bottom: 0;

        left: 0;

        width: 100%;


        height: 45px;


        background:

            linear-gradient(135deg, #022c22, #059669);


        color: white;


        display: flex;

        align-items: center;

        justify-content: center;


        box-shadow:

            0 -5px 20px rgba(0, 0, 0, .25);


        z-index: 999;



    }



    .footer-content {


        width: 95%;


        display: flex;


        align-items: center;


        justify-content: space-between;


        font-size: 13px;


    }



    .footer-logo {


        font-size: 18px;

        font-weight: bold;


        color: #fde047;


    }



    .footer-links {


        display: flex;

        gap: 20px;


    }



    .footer-links a {


        color: white;

        text-decoration: none;


        transition: .3s;


    }



    .footer-links a:hover {


        color: #fde047;


        transform: translateY(-2px);


    }



    /* MOBILE */


    @media(max-width:800px) {


        .mbd-footer {


            height: auto;

            padding: 10px 0;


        }



        .footer-content {


            flex-direction: column;

            gap: 5px;


        }



        .footer-links {


            display: none;


        }


    }
</style>