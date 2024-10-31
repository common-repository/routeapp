<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Titillium+Web:300,400,600,700"/>
<div id="route-login-form">
    <div class="form-wrapper">
        <div class="form-container">
            <div class="form">
                <p>It looks like your email address is linked to another store in our system.</p>
                <p>Please sign in below to link this installation with your existing account.</p>
                <form action="<?php echo get_rest_url(null, 'route/user_login'); ?>" method="POST">
                    <label class="form-label">Email Address</label>
                    <input name="username" class="form-input"  type="text" value="<?php echo $this->get_current_email() ?>"/>
                    <label class="form-label">Password</label>
                    <input class="form-input" name="password" type="password"/>
                    <span>By proceeding, you are agreeing to our <a href="https://route.com/terms-and-conditions/"  target="_blank" class="terms">Terms and Conditions</a></span>
                    <button><span>Continue</span></button>
                </form>
                <p><a href="https://dashboard.route.com/forgot-password" target="_blank">Forgot Password</a></p>
            </div>
        </div>
    </div>
</div>
<div id="routeapp-modals-overlay" style="z-index: 901;"></div>