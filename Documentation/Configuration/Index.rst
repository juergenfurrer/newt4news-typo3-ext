.. include:: ../Includes.txt

.. _configuration:

=============
Configuration
=============

To configurate this extension, you have to add the static template of Newt4News

After adding the static, you will find the constants in the Constant editor:

=====================================  ==========  ================================================================  =======================================
Property:                              Data type:  Description:                                                      Default:
=====================================  ==========  ================================================================  =======================================
settings.field.istopnews               boolean     Set visibility for Top-News in the from                           1
settings.field.title                   boolean     Set visibility for Title in the from                              1
settings.field.teaser                  boolean     Set visibility for Teaser in the from                             1
settings.field.bodytext                boolean     Set visibility for Bodytext in the from                           1
settings.field.datetime                boolean     Set visibility for Datetime in the from                           1
settings.field.archive                 boolean     Set visibility for Archive-Datetime in the from                   1
settings.field.image                   integer     Set count of images in the from                                   1 (0-6)
settings.field.showinpreview           integer     Set count of "Show in preview" in the from                        1 (0-6)
settings.field.imagealt                integer     Set count of Image-Alternative in the from                        1 (0-6)
settings.field.imagedesc               integer     Set count of Image-Description in the from                        1 (0-6)
settings.field.relatedfile             integer     Set count of Related-Files in the from                            1 (0-6)
settings.field.categories              boolean     Set visibility for Top-News in the from                           1
-------------------------------------  ----------  ----------------------------------------------------------------  ---------------------------------------
settings.required.title                boolean     Set Title required                                                1
settings.required.teaser               boolean     Set Teaser required
settings.required.bodytext             boolean     Set Bodytext required
settings.required.datetime             boolean     Set Datetime required                                             1
                                                   (If dateTimeNotRequired is set this setting is not used)
settings.required.archive              boolean     Set Archive-Datetime required
settings.required.image                integer     Set count of Image required
settings.required.imagealt             integer     Set count of Image-Alternative required
settings.required.imagedesc            integer     Set count of Image-Description required
settings.required.relatedfile          integer     Set count of Related-Files required
settings.required.categories           boolean     Set fields required
-------------------------------------  ----------  ----------------------------------------------------------------  ---------------------------------------
settings.value.showinpreview           integer     Sets default value of "Show in preview"
-------------------------------------  ----------  ----------------------------------------------------------------  ---------------------------------------
settings.list.orderField               string      Order-field for news-list                                         uid
settings.list.orderDirection           string      Order-direction for news-list                                     desc
=====================================  ==========  ================================================================  =======================================

[tsref:plugin.tx_newt4news]

.. toctree::
   :maxdepth: 5
   :titlesonly:

   AddEndpoints/Index
