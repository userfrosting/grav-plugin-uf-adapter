# Uf Adapter Plugin

This adapter allows you to use your UF templates, assets, and config variables in [Grav CMS](http://github.com/getgrav/grav). It is implemented as a Grav plugin.

You can add a Grav blog to your UserFrosting project by creating a `blog/` directory in your main UserFrosting project directory, and then installing Grav to `blog/`:

```bash
myUserFrostingProject/
├── app/
├── blog/
|   └── <Grav installation goes here>
├── build/
├── licenses/
├── migrations/
├── public
└── webserver-configs/
```

When this plugin is installed to your Grav blog, it will add the following services to Grav's dependency injection container:

- `ufAssets` - UserFrosting's asset loader
- `ufConfig` - UserFrosting's `Config` object, which contains the [merged result of all config files](https://learn.userfrosting.com/sprinkles/contents#config) from your Sprinkles.
- `ufLocator` - `UniformResourceLocator` to help find and resolve resources in your Sprinkles.

The plugin will then use these services to do the following:

- Load UserFrosting templates from any Sprinkles declared in your `sprinkles.json`, into Grav's Twig view.  These will be loaded _before_ any and all Grav templates.  Namespaced template paths for each Sprinkle will also be added.
- Add the UserFrosting asset loader to Twig.  Since Grav already has an `assets` Twig variable, UserFrosting's asset loader will be aliased as `ufAssets` in Twig.
- Merge the following UF variables into Grav's `site` variable:

- `site.uri.public`
- `site.title`
- `site.author`
- `site.analytics`
- `site.debug`

## Installation

Installing the Uf Adapter plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install uf-adapter

This will install the Uf Adapter plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/uf-adapter`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `uf-adapter`. You can find these files on [GitHub](https://github.com/alexander-weissman/grav-plugin-uf-adapter) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/uf-adapter
	
> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/uf-adapter/uf-adapter.yaml` to `user/config/plugins/uf-adapter.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```

Next, you will need to set a `site.uri.main` variable in your **UserFrosting** configuration, so that Grav knows where the main site directory is located.  This is important, because inside Grav, `site.uri.public` will end up pointing to **Grav**'s root url (which is not necessarily the same).

Finally, you will need to add the following dependencies to Grav's `composer.json` file:

```
    "require-dev": {
        "userfrosting/assets": "^4.0.1",
        "userfrosting/config": "^4.0.0",
        "vlucas/phpdotenv": "^2"
    },
    "autoload": {
        "files": [
            "../app/defines.php",
            "../app/helpers.php"
        ]
    }
```

and then run `composer update` in the `blog/` directory.  Unfortunately, this is the only way to add Composer dependencies to Grav at the moment.

## Usage

You should create a [Grav theme](https://learn.getgrav.org/themes/theme-basics) that extends the UserFrosting templates imported by this plugin.  For example, you might create a theme that contains the following base template:

```
{% extends 'layouts/basic.html.twig' %}

{% block stylesheets_site %}
    <!-- Include main CSS asset bundle -->
    {{ ufAssets.css() | raw }}
{% endblock %}

{% block body_matter %}
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <h1>{{ page.header.title }}</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            {{ page.content }}
        </div>
    </div>
{% endblock %}

{% block scripts_site %}
    <!-- Load jQuery -->
    <script src="//code.jquery.com/jquery-2.2.4.min.js" ></script>
    <!-- Fallback if CDN is unavailable -->
    <script>window.jQuery || document.write('<script src="{{ ufAssets.url('assets://vendor/jquery/dist/jquery.min.js', true) }}"><\/script>')</script>

    {{ ufAssets.js() | raw }}
{% endblock %}
```

As you can see, we've extended UserFrosting's core `layouts/basic.html.twig`, but we've overridden the `stylesheets_site` and `scripts_site` blocks to use the `ufAssets` asset manager variable.  If we didn't do this, the references to `assets` in the base template would resolve to Grav's asset manager instead (which we might not want).

## To Do

- [ ] Import all `site` config values from UF into Twig?
- [ ] Figure out how to remove requirement for `site.uri.main` config variable
- [ ] Maybe alias Grav's asset manager, instead of our own?
