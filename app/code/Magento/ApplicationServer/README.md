# Magento_ApplicationServer module

The Magento_ApplicationServer module provide posibillity to use Appllication Server (Container) to decrese boostraping time of each request 


To use it you need run service the run `bin/magento server:run`  cli command that start http port on 9501 port for graphql area and proxy all graphql queries to this webserver

Example nginx confiiguration : 
```
location /graphql {
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

    proxy_pass http://app:9501/graphql;
}
```
## Extensibility

Extension developers can interact with the Magento_ApplicationServer module. For more information about the Magento extension mechanism, see [Magento plug-ins](https://devdocs.magento.com/guides/v2.4/extension-dev-guide/plugins.html).

[The Magento dependency injection mechanism](https://devdocs.magento.com/guides/v2.4/extension-dev-guide/depend-inj.html) enables you to override the functionality of the Magento_ApplicationServer module.

## Additional information
