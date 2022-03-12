.. include:: ../Includes.txt

.. _configuration:

=============
Configuration
=============

To configurate this extension, you have to add the static template of Newt4News

After adding the static, you will find the constants in the Constant editor:

=====================================  ==========  ================================================================
Property:                              Data type:  Description:
=====================================  ==========  ================================================================
settings.field.*                       boolean     Activate/Deactivate fields

                                                   .. code-block:: ts

                                                      istopnews = 1
                                                      title = 1
                                                      teaser = 1
                                                      bodytext = 1
                                                      datetime = 1
                                                      archive = 1
                                                      image = 1
                                                      showinpreview = 1
                                                      imagealt = 1
                                                      imagedesc = 1
                                                      relatedfile = 1
                                                      categories = 1

-------------------------------------  ----------  ----------------------------------------------------------------
settings.required.*                    boolean     Sets fields required

                                                   .. code-block:: ts

                                                      title = 1
                                                      teaser =
                                                      bodytext =
                                                      datetime =
                                                      archive =
                                                      image =
                                                      imagealt =
                                                      imagedesc =
                                                      relatedfile =
                                                      categories =

-------------------------------------  ----------  ----------------------------------------------------------------
settings.value.*                       mixed       Sets fields default-value

                                                   .. code-block:: ts

                                                      showinpreview =

=====================================  ==========  ================================================================

[tsref:plugin.tx_newt4news]

.. toctree::
   :maxdepth: 5
   :titlesonly:

   AddEndpoints/Index
